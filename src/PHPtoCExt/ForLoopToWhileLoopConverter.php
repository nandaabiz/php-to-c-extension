<?php 
namespace PHPtoCExt;

class ForLoopToWhileLoopConverter extends Converter
{
  public function convert()
  {
    $parser = new \PhpParser\Parser(new \PhpParser\Lexer);
    $serializer = new \PhpParser\Serializer\XML();

    try {
      $stmts = $parser->parse($this->code);

      $codeLines = explode("\n", $this->code);
      $codeASTXML = $serializer->serialize($stmts);
      $codeASTXMLLines = explode("\n", $codeASTXML);

      $forLoopInfoIndexes = array();
      $index = 0;
      foreach($codeASTXMLLines as $line) {
        if (strpos(trim($line), "<node:Stmt_For>") === 0) {
          $forLoopInfoIndexes[] = $index;
        }
        $index ++;
      }
      
      $forLoopInfos = array();
      foreach($forLoopInfoIndexes as $index) {
        $startLineInfo = $codeASTXMLLines[$index + 2];
        $endLineInfo = $codeASTXMLLines[$index + 5];
        $forLoopInfo = new \stdClass();
        $forLoopInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$startLineInfo);
        $forLoopInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$endLineInfo);
        $forLoopInfo->originalCode = trim(implode("\n", array_slice($codeLines, $forLoopInfo->startLine-1, $forLoopInfo->endLine - $forLoopInfo->startLine + 1)));
        $forLoopInfo->bodyCode = trim(implode("\n",array_slice($codeLines, $forLoopInfo->startLine, $forLoopInfo->endLine - $forLoopInfo->startLine - 1)));
        $forLoopStartLineCode = $codeLines[$forLoopInfo->startLine - 1];
        $forLoopIndentationCode = explode("for",$forLoopStartLineCode)[0];
        preg_match('/(?<=\()(.+)(?=\))/is', $forLoopStartLineCode, $match);
        $forLoopParenthesisCode = $match[0];
        $forLoopParenthesisCodeComps = explode(";", $forLoopParenthesisCode);
        $forLoopInfo->initCode = trim($forLoopParenthesisCodeComps[0]);
        $forLoopInfo->conditionCode = trim($forLoopParenthesisCodeComps[1]);
        $forLoopInfo->incrementalCode = trim($forLoopParenthesisCodeComps[2]);
        $convertedWhileLoopCode = $forLoopInfo->initCode
          .";\n"
          .$forLoopIndentationCode
          ."while({$forLoopInfo->conditionCode}){\n"
          .$forLoopIndentationCode 
          .$forLoopIndentationCode
          .$forLoopInfo->bodyCode."\n"
          .$forLoopIndentationCode 
          .$forLoopIndentationCode 
          .$forLoopInfo->incrementalCode.";\n"
          .$forLoopIndentationCode
          ."}\n";
        $forLoopInfo->convertedWhileLoopCode = $convertedWhileLoopCode; 
        $forLoopInfos[] = $forLoopInfo;

      }

      foreach($forLoopInfos as $forLoopInfo) {
        $this->code = str_replace($forLoopInfo->originalCode, $forLoopInfo->convertedWhileLoopCode, $this->code);
      }
    } catch (\PhpParser\Error $e) {
      throw new ConverterException("PHP Parser Error: ".$e->getMessage());
    }

    return $this->code;
  }
}