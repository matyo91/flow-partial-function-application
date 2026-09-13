<?php

declare(strict_types=1);

/**
 * Conservative PFA/FCC opportunity scanner.
 *
 * PhpToken::tokenize(): one object per token, line/pos always present,
 * isIgnorable() drops whitespace/comments/open-tag.
 */

final class ScanConfig
{
}

final class Finding
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $kind,
        public readonly string $snippet,
        public readonly string $suggestion,
    ) {
    }
}

/**
 * @return list<string>
 */
function findPhpFiles(string $root): array
{
    if (is_file($root) && str_ends_with($root, '.php')) {
        return [$root];
    }

    if (!is_dir($root)) {
        return [];
    }

    $skip = ['vendor' => true, 'var' => true, 'node_modules' => true, '.git' => true];
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $info) {
        if (!$info instanceof SplFileInfo || !$info->isFile()) {
            continue;
        }
        if ($info->getExtension() !== 'php') {
            continue;
        }

        $parts = explode(DIRECTORY_SEPARATOR, $info->getPathname());
        $blocked = false;
        foreach ($parts as $part) {
            if (isset($skip[$part])) {
                $blocked = true;
                break;
            }
        }
        if ($blocked) {
            continue;
        }

        $files[] = $info->getPathname();
    }

    sort($files);

    return $files;
}

/**
 * @return list<Finding>
 */
function scanFile(string $file, ScanConfig $config): array
{
    unset($config);
    $source = file_get_contents($file);
    if ($source === false) {
        return [];
    }

    return scanSource($source, $file);
}

/**
 * @return list<Finding>
 */
function scanSource(string $source, string $file): array
{
    $tokens = PhpToken::tokenize($source, TOKEN_PARSE);
    $findings = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; ++$i) {
        if (!$tokens[$i]->is(T_FN)) {
            continue;
        }

        $start = $i;
        if ($i > 0) {
            $prev = prevSignificant($tokens, $i - 1);
            if ($prev !== null && $tokens[$prev]->is(T_STATIC)) {
                $start = $prev;
            }
        }

        $parsed = parseArrow($tokens, $i, $source, $file, $start);
        if ($parsed instanceof Finding) {
            $findings[] = $parsed;
        }
    }

    return $findings;
}

/**
 * @param list<PhpToken> $tokens
 */
function prevSignificant(array $tokens, int $i): ?int
{
    while ($i >= 0) {
        if (!$tokens[$i]->isIgnorable()) {
            return $i;
        }
        --$i;
    }

    return null;
}

/**
 * @param list<PhpToken> $tokens
 */
function nextSignificant(array $tokens, int $i): ?int
{
    $n = count($tokens);
    while ($i < $n) {
        if (!$tokens[$i]->isIgnorable()) {
            return $i;
        }
        ++$i;
    }

    return null;
}

/**
 * @param list<PhpToken> $tokens
 */
function parseArrow(array $tokens, int $fnIndex, string $source, string $file, int $snippetStart): ?Finding
{
    $i = nextSignificant($tokens, $fnIndex + 1);
    if ($i === null || $tokens[$i]->text !== '(') {
        return null;
    }

    $param = parseSingleParam($tokens, $i);
    if ($param === null) {
        return null;
    }
    [$paramName, $i] = $param;

    $i = nextSignificant($tokens, $i + 1);
    if ($i === null) {
        return null;
    }

    if ($tokens[$i]->text === ':') {
        $i = skipType($tokens, $i + 1);
        if ($i === null) {
            return null;
        }
        $i = nextSignificant($tokens, $i);
        if ($i === null) {
            return null;
        }
    }

    if (!$tokens[$i]->is(T_DOUBLE_ARROW)) {
        return null;
    }

    $call = parseUnaryCall($tokens, $i + 1, $paramName);
    if ($call === null) {
        return null;
    }

    [$kind, $suggestion, $endIndex] = $call;

    $after = nextSignificant($tokens, $endIndex + 1);
    if ($after !== null && !isArrowTerminator($tokens[$after])) {
        return null;
    }

    $startPos = $tokens[$snippetStart]->pos;
    $endPos = $tokens[$endIndex]->pos + strlen($tokens[$endIndex]->text);
    $snippet = trim(substr($source, $startPos, $endPos - $startPos));

    return new Finding($file, $tokens[$snippetStart]->line, $kind, $snippet, $suggestion);
}

/**
 * @param list<PhpToken> $tokens
 *
 * @return array{string, int}|null
 */
function parseSingleParam(array $tokens, int $openParen): ?array
{
    $i = nextSignificant($tokens, $openParen + 1);
    if ($i === null) {
        return null;
    }

    if ($tokens[$i]->is(T_ELLIPSIS) || $tokens[$i]->text === '&') {
        return null;
    }

    if ($tokens[$i]->is(T_ATTRIBUTE) || $tokens[$i]->text === '#') {
        return null;
    }

    if (!$tokens[$i]->is(T_VARIABLE)) {
        $i = skipType($tokens, $i);
        if ($i === null) {
            return null;
        }
        $i = nextSignificant($tokens, $i);
        if ($i === null) {
            return null;
        }
    }

    if ($tokens[$i]->text === '&') {
        return null;
    }
    if ($tokens[$i]->is(T_ELLIPSIS)) {
        return null;
    }
    if (!$tokens[$i]->is(T_VARIABLE)) {
        return null;
    }

    $name = $tokens[$i]->text;
    $after = nextSignificant($tokens, $i + 1);
    if ($after === null || $tokens[$after]->text !== ')') {
        return null;
    }

    return [$name, $after];
}

/**
 * Skip a parameter type. Returns index of the first token *after* the type,
 * or the current index if no type was consumed.
 *
 * @param list<PhpToken> $tokens
 */
function skipType(array $tokens, int $i): ?int
{
    $n = count($tokens);
    $i = nextSignificant($tokens, $i);
    if ($i === null) {
        return null;
    }

    $saw = false;
    while ($i < $n) {
        $t = $tokens[$i];
        if ($t->isIgnorable()) {
            ++$i;
            continue;
        }
        if ($t->is([T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_ARRAY, T_CALLABLE, T_STATIC, T_NS_SEPARATOR])) {
            $saw = true;
            ++$i;
            continue;
        }
        if ($t->text === '?' || $t->text === '|' || $t->text === '&') {
            $saw = true;
            ++$i;
            continue;
        }
        break;
    }

    return $saw ? $i : $i;
}

/**
 * @param list<PhpToken> $tokens
 *
 * @return array{string, string, int}|null
 */
function parseUnaryCall(array $tokens, int $from, string $paramName): ?array
{
    $i = nextSignificant($tokens, $from);
    if ($i === null) {
        return null;
    }

    $callee = parseCallee($tokens, $i, $paramName);
    if ($callee === null) {
        return null;
    }
    [$calleeText, $receiverIsParam, $i] = $callee;

    $i = nextSignificant($tokens, $i + 1);
    if ($i === null || $tokens[$i]->text !== '(') {
        return null;
    }

    $args = parseCallArgs($tokens, $i, $paramName);
    if ($args === null) {
        return null;
    }
    [$argKind, $restSource, $closeIndex] = $args;

    if ($receiverIsParam) {
        return null;
    }

    if ($argKind === 'only-param') {
        return ['fcc', $calleeText.'(...)', $closeIndex];
    }

    if ($argKind === 'param-then-bound') {
        return ['pfa', $calleeText.'(?, '.$restSource.')', $closeIndex];
    }

    return null;
}

/**
 * @param list<PhpToken> $tokens
 *
 * @return array{string, bool, int}|null text, receiverIsParam, index of last callee token
 */
function parseCallee(array $tokens, int $i, string $paramName): ?array
{
    if ($tokens[$i]->is([T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE])) {
        $name = $tokens[$i]->text;
        $next = nextSignificant($tokens, $i + 1);
        if ($next !== null && $tokens[$next]->is(T_DOUBLE_COLON)) {
            $method = nextSignificant($tokens, $next + 1);
            if ($method === null || !$tokens[$method]->is(T_STRING)) {
                return null;
            }

            return [$name.'::'.$tokens[$method]->text, false, $method];
        }

        return [$name, false, $i];
    }

    if ($tokens[$i]->is(T_VARIABLE)) {
        $receiver = $tokens[$i]->text;
        $op = nextSignificant($tokens, $i + 1);
        if ($op === null || !$tokens[$op]->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
            return null;
        }
        $method = nextSignificant($tokens, $op + 1);
        if ($method === null || !$tokens[$method]->is(T_STRING)) {
            return null;
        }

        return [$receiver.'->'.$tokens[$method]->text, $receiver === $paramName, $method];
    }

    return null;
}

/**
 * @param list<PhpToken> $tokens
 *
 * @return array{string, string, int}|null kind, rest source, close-paren index
 */
function parseCallArgs(array $tokens, int $openParen, string $paramName): ?array
{
    $i = nextSignificant($tokens, $openParen + 1);
    if ($i === null) {
        return null;
    }

    if ($tokens[$i]->text === ')') {
        return null;
    }

    if ($tokens[$i]->is(T_ELLIPSIS) || $tokens[$i]->text === '&') {
        return null;
    }

    if (!$tokens[$i]->is(T_VARIABLE) || $tokens[$i]->text !== $paramName) {
        return null;
    }

    $afterFirst = nextSignificant($tokens, $i + 1);
    if ($afterFirst === null) {
        return null;
    }

    if ($tokens[$afterFirst]->text === ')') {
        return ['only-param', '', $afterFirst];
    }

    if ($tokens[$afterFirst]->text !== ',') {
        return null;
    }

    $depth = 1;
    $n = count($tokens);
    for ($j = $afterFirst + 1; $j < $n; ++$j) {
        $t = $tokens[$j];
        if ($t->is(T_VARIABLE) && $t->text === $paramName) {
            return null;
        }
        if ($t->text === '(') {
            ++$depth;
        } elseif ($t->text === ')') {
            --$depth;
            if ($depth === 0) {
                $rest = trim(substrTokenText($tokens, $afterFirst + 1, $j - 1));
                if ($rest === '') {
                    return null;
                }

                return ['param-then-bound', $rest, $j];
            }
        } elseif ($t->text === ':' && $depth === 1) {
            return null;
        }
    }

    return null;
}

/**
 * @param list<PhpToken> $tokens
 */
function substrTokenText(array $tokens, int $from, int $to): string
{
    $out = '';
    for ($i = $from; $i <= $to; ++$i) {
        $out .= $tokens[$i]->text;
    }

    return $out;
}

function isArrowTerminator(PhpToken $token): bool
{
    return in_array($token->text, [')', ',', ';', ']', '}'], true);
}

function formatFinding(Finding $finding, string $displayPath): string
{
    return sprintf(
        "%s:%d\n\n%s\n\nPossible PHP 8.6 %s:\n\n%s\n",
        $displayPath,
        $finding->line,
        $finding->snippet,
        $finding->kind === 'pfa' ? 'PFA' : 'FCC',
        $finding->suggestion,
    );
}

function displayPath(string $file, array $roots): string
{
    foreach ($roots as $root) {
        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (str_starts_with($file, $root)) {
            return substr($file, strlen($root));
        }
    }

    return $file;
}

function countTokens(string $file): int
{
    $source = file_get_contents($file);
    if ($source === false) {
        return 0;
    }

    $n = 0;
    foreach (PhpToken::tokenize($source, TOKEN_PARSE) as $token) {
        if (!$token->isIgnorable()) {
            ++$n;
        }
    }

    return $n;
}
