<?php

require_once("L.php");

$c = file_get_contents("test.bib");

define("T_IDENTIFIER", "[^\\s\"#%'(){},=]*");


$WHITESPACE = new TokenType("WS", '[ \f\t\n]+');
$ATSTRING = new TokenType("ATSTRING", '@string');
$ATCOMMENT = new TokenType("ATCOMMENT", '@comment');
$ATPREAMBLE = new TokenType("ATPREAMBLE", '@preamble');
$COMMENT = new TokenType('COMMENT', '#.*\n');
$TYPE = new TokenType("TYPE", "@" . T_IDENTIFIER);
$KEY = new TokenType("KEY", T_IDENTIFIER);
$QUOTED = new TokenType("T_QUOTED", '"([^"]|\")*"');
$CLOSE = new TokenType("CLOSE", "}");
$OPEN = new TokenType('OPEN', '{');
$COMMA = new TokenType('COMMA', ',');
$EQUAL = new TokenType('EQ', '=');

$l = new Lexer(
    $COMMENT, $WHITESPACE,   $ATCOMMENT, $ATSTRING, $ATPREAMBLE,
    $OPEN, $CLOSE, $QUOTED, $TYPE, $KEY, $COMMA, $EQUAL
);
return $l->lex($c);