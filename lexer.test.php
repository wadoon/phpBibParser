<?php
/**
 * Created by PhpStorm.
 * User: weigl
 * Date: 14.01.17
 * Time: 01:44
 */

require_once('lexer.php');

$parser = new BibtexParser(file_get_contents("test.bib"));
$parser->Database();

print_r($parser->entries);


/*
$b = new BibtexParser();
$b->pos = 0;
$b->text = remove_comments(file_get_contents("test.bib"));
$b->ilexer = $lexer;

$b->Database();
*/