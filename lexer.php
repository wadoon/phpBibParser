<?php
define('T_BIBCOMMENT', '/#.*\n/i');
define("T_IDENTIFIER", "/^[^\\s\"#%'(){},=]+/i");
define("T_QUOTED", "/^\"([^\"]|\\\")*\"/i");
define("T_COMMENT_", "/^@comment/i");
define("T_STRING_", "/^@string/i");
define("T_PREAMBLE", "/^@preamble/i");
define("T_RECORD", "/^@([a-zA-Z:_0-9]*)/i");
define("T_NUMBER", "/^\\d+/");
define("T_CLOSE", "/^}/");
define("T_OPEN", "/^{/");
define("T_COMMA", "/^,/");
define("T_EQUAL", "/^=/");

class BibtexParser
{
    public $text;

    //public $pos;

    public function __construct($content)
    {
        $this->text = $content;
        //$this->pos = 0;
    }

    /**
     * consumes all whitespace
     */
    public function skipWhitespace()
    {
        $c = $this->cchar();
        if ($c == ' ' || $c == "\n" || $c == "\t" || $c == "\f") {
            $matches = array();
            if (preg_match("/^[ \n\t\f]+/", $this->text, $matches)) {
                $this->forward(strlen($matches[0]));
            }
        }
    }


    private $lastRegex = false;
    private $lastMatch = array();

    private function forward($n)
    {
        $this->text = substr($this->text, $n);
        $this->lastRegex = false;
    }

    /**
     * returns true, if we the current residual string matches the given regex.
     * @param $regex
     * @return bool
     */
    public function lookat($regex)
    {
        if ($this->lastRegex === $regex) {
            return $this->lastMatch[0];
        }

        $this->skipWhitespace();
        $this->lastRegex = $regex;
        $m = preg_match($regex, $this->text, $this->lastMatch);
        if (!$m) return false;
        else return $this->lastMatch[0];
    }

    /**
     * match current regex at position
     * @param $regex
     * @return bool
     * @throws Exception
     */
    public function mc($regex)
    {
        $v = $this->lookat($regex);
        if ($v) {
            $this->forward(strlen($v));
            return $v;
        } else {
            throw new Exception("try to match $regex without success, current character '" .
                $this->cchar() . "'Residual: " . substr($this->text, 0, 100));
        }
    }

    public function consumeUntil($tok)
    {
        $regex = "/(.*)$tok/";
        return $this->mc($regex);
    }


    public function cchar()
    {
        if (strlen($this->text) == 0) return '';
        return $this->text[0];
    }

    //***********************************************************************************

    public $stringEntries = array();
    public $entries = array();

    function Database()
    {
        do {
            if ($this->lookat(T_COMMENT_)) {
                $this->Comment();
            } else if ($this->lookat(T_PREAMBLE)) {
                $this->Preamble();
            } else if ($this->lookat(T_STRING_)) {
                $this->String();
            } else if ($this->lookat(T_RECORD)) {
                $this->Record();
            } else {
                break;
                //throw new Exception("no match: residual:" . substr($this->text, $this->pos, 100));
            }
        } while (true);
    }

    function Comment()
    {
        //@COMMENT dsfdsafdsajflsajflasjfl
        //@COMMENT fdsfdsfasfdsaf
        $this->mc(T_COMMENT_);
        //$this->mc(T_OPEN);
        $this->consumeUntil("\n");
        //$this->mc(T_CLOSE);
    }

    function String()
    {
        $this->mc(T_STRING_);
        $this->mc(T_OPEN);
        $fields = $this->Fields();
        $this->mc(T_CLOSE);

        $this->stringEntries = array_merge($this->stringEntries, $fields);
    }

    function Preamble()
    {
        $this->mc(T_PREAMBLE);
        $this->curlyText(); // need to matching
    }

    function Record()
    {
        $this->currentEntry = array();
        $this->mc(T_RECORD);
        $this->mc(T_OPEN);
        $value = $this->mc(T_IDENTIFIER);
        $this->mc(T_COMMA);
        $fields = $this->Fields();
        $this->mc(T_CLOSE);
        $this->entries[$value] = $fields;
    }

    function Fields()
    {
        $f = array();
        $prefix = "";
        while ($this->lookat(T_IDENTIFIER)) {
            /*if ($this->lookat(T_CLOSE)) {
                $this->mc(T_CLOSE);
                break;
            }*/
            $a = $this->Field();
            $f[$prefix . $a[0]] = $a[1];
            if ($this->lookat(T_COMMA))
                $this->mc(T_COMMA);
            if ($this->lookat("/^%(SNIP|--)/i")) {
                $prefix = "_";
                $this->mc("/^%(SNIP|--)/i");
            }
        }
        return $f;
    }

    function Field()
    {
        $name = $this->mc(T_IDENTIFIER);
        $this->mc(T_EQUAL);
        $value = $this->Value();
        return array($name, $value);
    }

    function Value()
    {
        if ($this->cchar() == ',')
            return "";
        elseif ($this->lookat(T_NUMBER)) {
            return $this->mc(T_NUMBER);
        } elseif ($this->lookat(T_QUOTED)) {
            return $this->mc(T_QUOTED);
        } elseif ($this->lookat(T_OPEN)) {
            return $this->curlyText();
        } elseif ($this->lookat(T_IDENTIFIER)) {
            $id = $this->mc(T_IDENTIFIER);
            while ($this->lookat("/^#/"))
                $this->mc("/^#/");
            return $this->stringEntries[$id] . $this->Value();
        } else {
            throw new Exception("no match for value");
        }
    }

    function curlyText()
    {
        //$this->loo(T_OPEN);
        $counter = 0;
        $start = 0;
        do {
            $c = $this->text[$start];
            switch ($c) {
                case '{':
                    $counter++;
                    break;
                case '}':
                    $counter--;
                    break;
            }
            #print "C: $counter , $c\n";
            $start++;
        } while ($counter > 0);
        $t = substr($this->text, 0, $start);
        $this->forward($start);
        return $t;
    }
}


function remove_comments($text)
{
    return preg_replace("/%[^\n,]*(?=,|\n)/", "", $text);
}
