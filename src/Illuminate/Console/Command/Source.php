<?php

namespace Illuminate\Console\Command;

use Closure;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class Source
{
    protected $tokens;

    protected $count;

    protected $index;

    /**
     * Get the command class for the file
     *
     * @param  string|array|\Symfony\Component\Finder\SplFileInfo $source
     *
     * @return void
     */

    public function __construct($source)
    {
        $source       = $this->getContents($source);
        $this->tokens = token_get_all($source);
        $this->count  = count($this->tokens);
        $this->index  = -1;
    }

    /**
     * Get the command class for the file
     *
     * @param  string|\Symfony\Component\Finder\SplFileInfo $file
     *
     * @return string
     */
    protected function getContents($source)
    {
        if ($source instanceof SplFileInfo) {
            $source = $source->getContents();
        }

        if (is_array($source)) {
            $source = implode(PHP_EOL, $source);
        }

        if (!Str::startsWith($source, '<?php')) {
            $source = '<?php ' . ltrim($source);
        }

        return $source;
    }

    protected function findToken($posibleTokens, Closure $where = null)
    {
        if ($hasWhere = ($where instanceof Closure)) {
            $where = Closure::bind($where, $this);
        }
        $posibleTokens = (array) $posibleTokens;
        foreach ($this->getTokens() as $index => $token) {
            if (in_array($token['search'], $posibleTokens) &&
                (!$hasWhere || ($where($index) && ($index = $this->index)))
            ) {
                return $this->index = $index;
            }
        }

        return false;
    }

    protected function getTokenContent($posibleTokens, $start = null)
    {
        $content       = null;
        $posibleTokens = (array) $posibleTokens;
        $endTokens     = ['{', ';', '('];
        foreach ($this->getTokens($start) as $index => $token) {
            if (($content !==null && $token['number'] == T_WHITESPACE) ||
                in_array($token['text'], $endTokens)
            ) {
                break;
            }
            if (in_array($token['number'], $posibleTokens)) {
                $content    .= $token['text'];
                $this->index = $index;
            }
        }

        return $content;
    }

    protected function getTokenContentUntil($posibleTokens, $after = null)
    {
        $content       = null;
        $posibleTokens = (array) $posibleTokens;
        $after         = (array) $after;
        foreach ($this->getTokens() as $index => $token) {
            if (in_array($token['search'], $posibleTokens)) {
                break;
            }
            if ($token['number'] == T_WHITESPACE ||
                ($after && !in_array($token['search'], $after))
            ) {
                continue;
            }
            $content    .= $token['text'];
            $this->index = $index;
            $after       = null;
        }

        return $content === null ? $content: trim($content);
    }

    protected function getTokens($start = null)
    {
        $start = ($start ?: $this->index);
        for ($index = $start + 1; $index < $this->count; ++ $index) {
            yield $index => $this->getToken($index);
        }
    }

    protected function getToken($index)
    {
        $token = $this->tokens[$index];
        if (!is_array($token)) {
            $token = [null, $token];
        }

        return [
            'number' => $token[0],
            'text'   => $token[1],
            'search' => $token[0] === null ? $token[1] : $token[0]
        ];
    }

    public function getNamespace()
    {
        if ($this->findToken(T_NAMESPACE) &&
            ($namespace = $this->getTokenContent([T_STRING, T_NS_SEPARATOR]))
        ) {
            if (!Str::startsWith($namespace, '\\')) {
                $namespace = "\\$namespace";
            }

            return $namespace;
        }
    }

    public function getShortClassName()
    {
        if ($this->findToken(T_CLASS) &&
            ($className = $this->getTokenContent(T_STRING))
        ) {
            return $className;
        }
    }

    public function getClassName()
    {
        $namespace = $this->getNamespace();

        if ($className = $this->getShortClassName()) {
            return $namespace . '\\' . $className;
        }
    }

    public function getProperty($name)
    {
        $name = array_map(
            function ($name) {
                if (!Str::startsWith($name, '$')) {
                    $name = '$' . $name;
                }
                return $name;
            },
            (array) $name
        );

        $where = function ($index) use ($name) {
            $varname = $this->getTokenContent(T_VARIABLE, $index);
            return $varname && in_array($varname, $name);
        };

        if ($this->findToken([T_PRIVATE, T_PROTECTED, T_PUBLIC], $where)) {
            $properySource = $this->getTokenContentUntil(';', '=');
            return $properySource;
        }

        return false;
    }
}
