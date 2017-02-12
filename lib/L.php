<?php

class TokenType
{
    public $name, $regex, $callback;

    public function __construct($n, $r, $c = null)
    {
        $this->name = $n;
        $this->regex = $r;
        if ($c === null)
            $this->callback = function ($x) {
                return $x;
            };
        else
            $this->callback = $c;
    }
}

class Token
{
    public $type, $value, $position;

    public function __construct($n, $v)
    {
        $this->type = $n;
        $this->value = $v;
    }
}

class Lexer
{
    public $regex, $tokentypes;

    public function __construct()
    {
        $this->tokentypes = func_get_args();
        $re = array();
        foreach ($this->tokentypes as $t) {
            $re[] = "(?P<$t->name>$t->regex)";
        }
        $this->regex = "/^" . implode("|", $re) . "/Si";
#print($this->regex);
    }

    public function lex($string)
    {
        $pos = 0;
        $tokens = array();

        while ($pos < strlen($string)) {
            $matches = array();
            $m = preg_match($this->regex, substr($string, $pos), $matches);#, 0, $pos);

            if (!$m) {
                echo "no match, pos = $pos\n";
                echo "residual: ", substr($string, $pos);
                break;
            }

            foreach ($matches as $name => $content) {
                if ($content) {
                    //$cl = $t->callback;
                    //$tokens[] = new Token($t->name, $cl($value));
                    $t = new Token($name, $content);
                    echo $name, $content;
                    $tokens[] = $t;
                    $pos += strlen($content);
                    break;
                }
            }
        }
        return $tokens;
    }
}
