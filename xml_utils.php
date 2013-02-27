<?php

class xml_utils
{

  static function xmlstr_to_array($xmlstr) {
    $doc = new DOMDocument();
    $doc->loadXML($xmlstr);
    return xml_utils::domnode_to_array($doc->documentElement);
  }

  static function domnode_to_array($node) {
    $output = array();
    switch ($node->nodeType) {
    case XML_CDATA_SECTION_NODE:
    case XML_TEXT_NODE:
    case XML_ELEMENT_NODE:
      for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) { 
        $child = $node->childNodes->item($i);
        $v = xml_utils::domnode_to_array($child);
        if(isset($child->tagName)) {
          $t = $child->tagName;
          if(!isset($output[$t])) {
            $output[$t] = array();
          }
          $output[$t][] = $v;
        } elseif($v) {
          $output = $v; //(string) $v;
        }
      }
      if(is_array($output)) {

        if($node->attributes->length) {
          $a = array();
          foreach($node->attributes as $attrName => $attrNode) {
            $a[$attrName] = (string) $attrNode->value;
          }
          $output['@attributes'] = $a;
        }

        if($node->nodeType == XML_TEXT_NODE) {
          $output['@text'] = trim($node->textContent);
        }
      
        foreach ($output as $t => $v) {
          if(is_array($v) && count($v)==1 && $t!='@attributes') {
            //$output[$t] = $v[0];
          }
        }
      }
      break;
    }
    return $output;
  }
}
?>
