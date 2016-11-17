<?php

class TT {
    public $name, $regex, $callback;
    public function __construct($n, $r, $c = null) {
        $this->name = $n;
        $this->regex = $r;
        if($c === null)
            $this->callback = function ($x) { return $x; };
        else
            $this->callback = $c;
    }
}

class Token {
    public $type, $value, $position;
    public function __construct($n, $v) {
        $this->type = $n;
        $this->value = $v;
    }
}

class Lexer {
    public $regex, $tokentypes;

    public function __construct() {
        $this->tokentypes = func_get_args();
        $re = array();
        foreach($this->tokentypes as $t) {
            $re[] = "(?P<$t->name>$t->regex)";
        }
        $this->regex = "/^" . implode("|",$re) . "/Si";
        #print($this->regex);
    }

    public function lex($string) {
        $pos = 0;
        $tokens = array();

        while($pos < strlen($string)) {
            $matches = array();
            $m = preg_match($this->regex, substr($string,$pos), $matches);#, 0, $pos);

            if(!$m)  {
                // we got a problem, no ruled match
                echo "no match, pos = $pos\n";
                echo "residual: ", substr($string, $pos);
                break;
            }

            //print_r($matches);

            foreach($this->tokentypes as $t) {
                if(array_key_exists($t->name, $matches)
                    && $matches[$t->name] != null
                ) {
                    $value = $matches[$t->name];

 #                   echo "found: $t->name = '$value'\n";
                    $cl = $t->callback;
                    $tokens[] = new Token($t->name,
                                          $cl($value));
                    $pos += strlen($value);
#                    echo "new pos: $pos: >", substr($string, $pos), "<\n";
                    break;
                }
            }
        }
        return $tokens;
    }
}


$lexer = new Lexer(
    new TT("number", "\\d+"),
    new TT("q", "[q]"),
    new TT("ws", "[ ]+")
);

#print_r($lexer->lex("16161 q 16161"));

# A rough grammar (case-insensitive):
#
# Database  ::= (Junk '@' Entry)*
# Junk      ::= .*?
# Entry ::= Record
#       |   Comment
#       |   String
#       |   Preamble
# Comment   ::= "comment" [^\n]* \n     -- ignored
# String    ::= "string" '{' Field* '}'
# Preamble  ::= "preamble" '{' .* '}'   -- (balanced)
# Record    ::= Type '{' Key ',' Field* '}'
#       |   Type '(' Key ',' Field* ')' -- not handled
# Type  ::= Name
# Key   ::= Name
# Field ::= Name '=' Value
# Name      ::= [^\s\"#%'(){}]*
# Value ::= [0-9]+
#       |   '"' ([^'"']|\\'"')* '"'
#       |   '{' .* '}'          -- (balanced)


define("T_IDENTIFIER", "/^[^\s\"#%'(){},=]*/");
define("T_QUOTED", "/^\"([^\"]|\\\")*\"/");
define("T_COMMENT_", "/^@comment/i");
define("T_STRING_", "/^@string/i");
define("T_PREAMBLE", "/^@preamble/i");
define("T_RECORD", "/^@([a-zA-Z:_0-9]*)/i");
define("T_NUMBER", "/^\\d+/");
define("T_CLOSE", "/^}/");
define("T_OPEN", "/^{/");
define("T_COMMA", "/^,/");
define("T_EQUAL", "/^=/");

class BibtexParser {
    // Lexer functions
    public $text, $pos;

    public function skipWhitespace() {
        while($this->pos < strlen($this->text)) {
            $c = $this->cchar();
            if($c == ' ' || $c =="\n" || $c == "\t")
                $this->pos++;
            else
                break;
        }
    }

    public function lookat($regex) {
        $this->skipWhitespace();
        $matches = array();
        $m = preg_match($regex, substr($this->text,$this->pos), $matches);#, 0, $pos);
        //echo "r: $regex => $matches[0]\n";

        if(!$m) return false;
        else return $matches[0];
    }

    public function mc($regex){
        $v = $this->lookat($regex);
        if($v) {
            $this->pos += strlen($v);
            return $v;
        } else {
            throw new Exception("try to match $regex without success, current character '".
                                $this->cchar() ."' @$this->pos, Residual: " . substr($this->text, $this->pos, 100));
        }
    }

    public function consumeUntil($tok) {
        $regex = "/(.*)$tok/";
        return $this->mc($regex);
    }


    public function cchar() {
        return $this->text[$this->pos];
    }
    //***********************************************************************************


    function Database() {
        while($this->lookat(T_COMMENT_) ||
              $this->lookat(T_PREAMBLE) ||
              $this->lookat(T_STRING_) ||
              $this->lookat(T_RECORD)) {
            $this->Entry();
        }
    }

    function Entry() {
        if($this->lookat(T_COMMENT_)) {
            $this->Comment();
        } else if($this->lookat(T_PREAMBLE)) {
            $this->Preamble();
        } else if($this->lookat(T_STRING_)) {
            $this->String();
        } else if($this->lookat(T_RECORD)) {
            $this->Record();
        } else {
            throw new Exception("no match: residual:". substr( $this->text, $this->pos, 100));
        }
    }

    function Comment() {
        $this->mc(T_COMMENT_);
        $this->consumeUntil("\n");
    }

    function String() {
        $this->mc(T_STRING_);
        $this->mc(T_OPEN);
        $this->Fields();
        $this->mc(T_CLOSE);
    }

    function Preamble() {
        $this->mc(T_PREAMBLE);
        $this->curlyText(); // need to matching
    }

    function Record() {
        $this->mc(T_RECORD);
        $this->mc(T_OPEN);
        $value = $this->mc(T_IDENTIFIER);
        $this->mc(T_COMMA);
        $this->Fields();
    }

    function Fields(){
        $f = array();

        do {
            if($this->lookat(T_CLOSE)) {
                $this->mc(T_CLOSE);
                break;
            }
            $f[] = $this->Field();

            if($this->lookat(T_COMMA))
                $this->mc(T_COMMA);
            else
                break;

        } while(true);
        #print_r($f);
        return $f;
    }

    function Field() {
        $name = $this->mc(T_IDENTIFIER);
        $this->mc(T_EQUAL);
        $value = $this->Value();
        return array($name, $value);
    }

    function Value() {
        if($this->lookat(T_NUMBER)) {
            return $this->mc(T_NUMBER);
        } else if ($this->lookat(T_QUOTED)) {
            return $this->mc(T_QUOTED);
        } else if($this->lookat(T_OPEN)) {
            return $this->curlyText();
        } else if($this->lookat(T_IDENTIFIER)){
            return $this->mc(T_IDENTIFIER);
        } else {
            throw new Exception("no match for value");
        }
    }

    function curlyText() {
        //        $this->loo(T_OPEN);
        $counter = 0;
        $start = $this->pos;
        do {
            $c = $this->cchar();
            switch($c)
            {
            case '{': $counter++; break;
            case '}': $counter--; break;
            }
#            print "C: $counter , $c\n";
            $this->pos++;
        }while($counter > 0);
        $end = $this->pos;
        $t =  substr( $this->text, $start+1, ($end - $start -2));
        return $t;
    }
}

function remove_comments($text) {
    return preg_replace("/#[^\n,]*(?=,|\n)/", "", $text);
}

$b = new BibtexParser();
$b->pos = 0;
$b->text = remove_comments(file_get_contents("test.bib"));
$b->ilexer = $lexer;

$b->Database();


?>