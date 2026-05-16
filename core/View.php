<?php

namespace Core;

class View
{
    /**
     * Indikuje, že právě vykreslujeme layout. Volání render uvnitř layoutu
     * musí přeskočit použití layoutu, jinak vznikne rekurze.
     */
    protected static bool $renderingLayout = false;

    /**
     * Renderuje view s layoutem. Pokud existuje přepis ve views modulu, použije jej.
     * @param string $view
     * @param array $data
     * @param string $layout
     * @param string|null $module Pokud je zadán, hledá view nejprve v modulu
     * @return string
     */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main')
    {
        $content = '';
        $noLayout = !empty($data['__no_layout']);
        // Zkusit najít view ve všech modulech podle configu
        $modules = config("app.modules", []);

        foreach ($modules as $module) {
            $moduleViewPath = __DIR__ . "/../modules/$module/views/$view.php";
            if (file_exists($moduleViewPath)) {
                $content = self::captureFrom(__DIR__ . "/../modules/$module/views/", $view, $data);
                // Pokud modul poskytuje vlastní layout, použij jej
                if (file_exists(__DIR__ . "/../modules/$module/views/$layout.php")) {
                    $layout = "$module/$layout";
                }
                break;
            }
        }

        // Pokud žádný modul view neposkytuje, použij app/Views
        if (!$content) {
            $content = self::captureFrom(__DIR__ . '/../app/Views/', $view, $data);
        }
        if (!$content) {
            Logger::getInstance()->error("View not found: $view", ['modules' => $modules, 'searched_paths' => array_map(fn($m) => __DIR__ . "/../modules/$m/views/$view.php", $modules) + [__DIR__ . '/../app/Views/' . $view . '.php']]);
            throw new \Exception("View not found: $view");
        }
        
        // Rozhodnutí, zda aplikovat layout:
        // - Pokud právě vykreslujeme layout (vnitřní volání), layout nepoužijeme
        // - Pokud jde o partial/komponentu (cesta začíná na 'partials/' nebo 'components/'), layout nepoužijeme
        $isPartial = self::isPartialView($view);
        if ($noLayout || self::$renderingLayout || $isPartial) {
            // Bez layoutu – jen vytiskni obsah
            // Logger::getInstance()->debug("Rendering partial/no-layout: $view", ['modules' => $modules, 'content_length' => strlen($content), "content_preview" => substr($content, 0, 100)]);
            // print $content;
            return $content;
        }

        // Obalení do layoutu
        self::$renderingLayout = true;
        $data['content'] = $content;
        $wrapped = self::captureFrom(__DIR__ . '/../app/Views/', $layout, $data);
        self::$renderingLayout = false;

        // Logger::getInstance()->debug("Rendering view: $view with layout: $layout", ['data' => $data, 'modules' => $modules, 'content_length' => strlen($wrapped), "content_preview" => substr($wrapped, 0, 100)]);
        return $wrapped;
    }

    public static function module(string $module, string $view, array $data = [], string $layout = 'layouts/main')
    {
        // Pro kompatibilitu, volá render s modulem
        return self::render($view, $data, $layout, $module);
    }

    protected static function captureFrom(string $base, string $view, array $data): string
    {
        // ob_start();
        // extract($data);
        // require $base . $view . '.php';
        // return ob_get_clean();

        // Pokud soubor neexistuje, nic nenačítáme
        if (!file_exists($base . $view . '.php')) {
            return '';
        }

        extract($data);
        $filecontent = file_get_contents($base . $view . '.php');

        $splitNullCoalesce = function(string $expr): ?array {
            $len = strlen($expr);
            $depth = 0;
            $inSingle = false;
            $inDouble = false;

            for ($i = 0; $i < $len - 1; $i++) {
                $ch = $expr[$i];
                $next = $expr[$i + 1];

                if ($ch === "'" && !$inDouble) {
                    $inSingle = !$inSingle;
                    continue;
                }
                if ($ch === '"' && !$inSingle) {
                    $inDouble = !$inDouble;
                    continue;
                }

                if (!$inSingle && !$inDouble) {
                    if ($ch === '(') {
                        $depth++;
                    } elseif ($ch === ')') {
                        $depth--;
                    } elseif ($ch === '?' && $next === '?' && $depth === 0) {
                        $left = substr($expr, 0, $i);
                        $right = substr($expr, $i + 2);
                        return [trim($left), trim($right)];
                    }
                }
            }

            return null;
        };

        $evalExpr = function(string $expr) use (&$evalExpr, $data, $splitNullCoalesce) {
            $expr = trim($expr);
            if ($expr === '') {
                return null;
            }

            if (str_starts_with($expr, '#')) {
                return null;
            }

            // null-coalesce (??) na top-level
            $parts = $splitNullCoalesce($expr);
            if ($parts) {
                [$left, $right] = $parts;
                $leftVal = $evalExpr($left);

                 $isEmptyArray = is_array($leftVal) && $leftVal === [];
                return ($leftVal !== null && !$isEmptyArray) ? $leftVal : $evalExpr($right);
            }

            $first = $expr[0];
            $last = $expr[strlen($expr) - 1];
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                return substr($expr, 1, -1);
            }

            if (is_numeric($expr)) {
                return $expr + 0;
            }

            if (preg_match('/^([a-zA-Z_]\w*)\s*\((.*)\)$/s', $expr, $m)) {
                $fn = $m[1];
                $argsStr = trim($m[2]);

                $args = [];
                $current = '';
                $depth = 0;
                $inSingle = false;
                $inDouble = false;

                $len = strlen($argsStr);
                for ($i = 0; $i < $len; $i++) {
                    $ch = $argsStr[$i];

                    if ($ch === "'" && !$inDouble) {
                        $inSingle = !$inSingle;
                        $current .= $ch;
                        continue;
                    }
                    if ($ch === '"' && !$inSingle) {
                        $inDouble = !$inDouble;
                        $current .= $ch;
                        continue;
                    }

                    if (!$inSingle && !$inDouble) {
                        if ($ch === '(') {
                            $depth++;
                        } elseif ($ch === ')') {
                            $depth--;
                        } elseif ($ch === ',' && $depth === 0) {
                            $args[] = $current;
                            $current = '';
                            continue;
                        }
                    }

                    $current .= $ch;
                }

                if (trim($current) !== '' || $argsStr !== '') {
                    $args[] = $current;
                }

                $resolvedArgs = array_map(function($a) use ($evalExpr) {
                    return $evalExpr($a);
                }, $args);

                if (function_exists($fn) || is_callable($fn)) {
                    return call_user_func($fn, ...$resolvedArgs);
                }

                Logger::getInstance()->error("Function not found in template: $fn", ['expression' => $expr]);
                return null;
            }

            if ($expr[0] === '$') {
                $expr = substr($expr, 1);
            }

            if ($expr === '[]') {
                return [];
            }

            return array_key_exists($expr, $data) ? $data[$expr] : null;
        };

 // === Podmínky s operátory: !, &&, ||, ==, !=, <, >, <=, >= ===
        $scanTopLevel = function(string $expr, callable $onToken) {
            $len = strlen($expr);
            $depth = 0;
            $inSingle = false;
            $inDouble = false;

            for ($i = 0; $i < $len; $i++) {
                $ch = $expr[$i];

                if ($ch === "'" && !$inDouble) { $inSingle = !$inSingle; }
                elseif ($ch === '"' && !$inSingle) { $inDouble = !$inDouble; }
                elseif (!$inSingle && !$inDouble) {
                    if ($ch === '(') { $depth++; }
                    elseif ($ch === ')') { $depth--; }
                }

                if (!$inSingle && !$inDouble && $depth === 0) {
                    if ($onToken($i, $ch, $expr) === true) {
                        return true;
                    }
                }
            }

            return false;
        };

        $splitByOp = function(string $expr, string $op) use ($scanTopLevel): ?array {
            $pos = null;
            $scanTopLevel($expr, function($i, $ch, $s) use ($op, &$pos) {
                if (substr($s, $i, strlen($op)) === $op) {
                    $pos = $i;
                    return true;
                }
                return false;
            });

            if ($pos === null) return null;
            return [trim(substr($expr, 0, $pos)), trim(substr($expr, $pos + strlen($op)))];
        };

        $findComparison = function(string $expr) use ($scanTopLevel): ?array {
            $ops = ['==', '!=', '>=', '<=', '>', '<'];
            $found = null;

            $scanTopLevel($expr, function($i, $ch, $s) use ($ops, &$found) {
                foreach ($ops as $op) {
                    if (substr($s, $i, strlen($op)) === $op) {
                        $found = [$i, $op];
                        return true;
                    }
                }
                return false;
            });

            if (!$found) return null;
            [$pos, $op] = $found;

            return [trim(substr($expr, 0, $pos)), $op, trim(substr($expr, $pos + strlen($op)))];
        };

        $evalCond = function(string $expr) use (&$evalCond, $evalExpr, $splitByOp, $findComparison) {
            $expr = trim($expr);
            if ($expr === '') return false;

            // OR
            if ($parts = $splitByOp($expr, '||')) {
                return $evalCond($parts[0]) || $evalCond($parts[1]);
            }

            // AND
            if ($parts = $splitByOp($expr, '&&')) {
                return $evalCond($parts[0]) && $evalCond($parts[1]);
            }

            // Comparison
            if ($cmp = $findComparison($expr)) {
                [$left, $op, $right] = $cmp;

                $leftVal  = $evalCond($left);  // umožní i (a && b) jako operand
                $rightVal = $evalCond($right);

                return match ($op) {
                    '==' => $leftVal == $rightVal,
                    '!=' => $leftVal != $rightVal,
                    '>'  => $leftVal >  $rightVal,
                    '<'  => $leftVal <  $rightVal,
                    '>=' => $leftVal >= $rightVal,
                    '<=' => $leftVal <= $rightVal,
                };
            }

            // Unary !
            if (str_starts_with($expr, '!')) {
                return !$evalCond(substr($expr, 1));
            }

            // Závorky
            if ($expr[0] === '(' && $expr[strlen($expr) - 1] === ')') {
                $inner = trim(substr($expr, 1, -1));
                return $evalCond($inner);
            }

            // Fallback na hodnoty/volání funkcí/proměnné
            return (bool)$evalExpr($expr);
        };

        // Zpracování @if/@elseif/@else/@endif (včetně vnořených)
        $renderIfBlocks = function(string $tpl) use (&$renderIfBlocks, $evalCond) {
            $pattern = '/@if\s*\((.*?)\)((?:(?!@if).)*?)@endif/s';

            while (preg_match($pattern, $tpl)) {
                $tpl = preg_replace_callback($pattern, function($m) use (&$renderIfBlocks, $evalCond) {
                    $cond = trim($m[1]);
                    $body = $m[2];

                    $parts = [];
                    $conds = [$cond];
                    $offset = 0;

                    if (preg_match_all('/@elseif\s*\((.*?)\)|@else/s', $body, $mm, PREG_OFFSET_CAPTURE)) {
                        foreach ($mm[0] as $i => $hit) {
                            $pos = $hit[1];
                            $parts[] = substr($body, $offset, $pos - $offset);

                            if (str_starts_with($hit[0], '@elseif')) {
                                $conds[] = trim($mm[1][$i][0] ?? '');
                            } else {
                                $conds[] = null;
                            }

                            $offset = $pos + strlen($hit[0]);
                        }
                    }

                    $parts[] = substr($body, $offset);

                    foreach ($parts as $i => $block) {
                        $c = $conds[$i] ?? null;
                        if ($c === null) {
                            return $renderIfBlocks($block);
                        }
                        if ($evalCond($c)) {
                            return $renderIfBlocks($block);
                        }
                    }

                    return '';
                }, $tpl);
            }

            return $tpl;
        };

        $filecontent = $renderIfBlocks($filecontent);

        $rendered = preg_replace_callback('/{{\s*(.*?)\s*}}/s', function($matches) use ($evalExpr) {
            $val = $evalExpr($matches[1]);
            return $val === null ? '' : (string)$val;
        }, $filecontent);

        return $rendered;
    }

    /**
     * Určí, zda jde o partial/komponentu, která se nemá obalovat layoutem.
     */
    protected static function isPartialView(string $view): bool
    {
        return str_starts_with($view, 'partials/')
            || str_starts_with($view, 'components/');
    }
}
