<?php
class NP_ChoppedDisc extends NucleusPlugin {
    function getEventList() { return array(); }
    function getName() { return 'Chopped description'; }
    function getAuthor() { return 'nakahara21, yamamoto'; }
    function getURL() { return 'https://github.com/NucleusCMS/NP_ChoppedDisc'; }
    function getVersion() { return '0.8'; }
    function getDescription() {
        return 'Chopped description. &lt;%ChoppedDisc(250,1)%&gt;';
    }
 
    function doTemplateVar(&$item, $maxLength = 250, $addHighlight = 0) {
        global $CONF, $manager, $member, $catid;
        global $query;
 
        if ($manager->pluginInstalled('NP_HighlightSource')) {
            $tempPlugin =& $manager->getPlugin('NP_HighlightSource');
            preg_replace_callback("#<hs(|:[^>]+?)>(.*?)</hs>#s", array(&$tempPlugin, 'phpHighlight'), $item->body); 
            preg_replace_callback("#<hs(|:[^>]+?)>(.*?)</hs>#s", array(&$tempPlugin, 'phpHighlight'), $item->more); 
        }
 
        $syndicated = strip_tags($item->body);
        $syndicated .= strip_tags($item->more);
        $syndicated = preg_replace("/[\r\n]/","",$syndicated);
 
        $syndicated = $this->chopStr($syndicated, $query, $maxLength);
 
        if ($addHighlight) {
            global $currentTemplateName;
            $template =& $manager->getTemplate($currentTemplateName);
            echo highlight($syndicated, $this->highlights, $template['SEARCH_HIGHLIGHT']);
        } else {
            echo $syndicated;
        }
    }
    
    function parseHighlight($query) {
        // get rid of quotes
        $query = preg_replace('/\'|"/','',$query);
 
        if (!$query) return array();
 
        $aHighlight = explode(' ', $query);
 
        foreach ($aHighlight as $i=>$v) {
            $aHighlight[$i] = trim($v);
        }
 
            return $aHighlight;
    }
 
    function splitLastStr($str, $width=5) {
        $posn = (mb_strwidth($str) > $width)? mb_strwidth($str) - $width: 0;
        $resArray[0] = ($posn)? mb_strcut($str, 0, $posn, _CHARSET): '';
        $resArray[1] = ($posn)? mb_strcut($str, $posn, $width + 2, _CHARSET): $str;
        return $resArray;
    }
 
    function chopStr($str, $query, $maxLength) {
        $addsum = 0;
        $searchclass = new SEARCH($query);
        $highlight      = $searchclass->inclusive;
        $this->highlights = $this->parseHighlight($highlight);
 
        if(mb_strwidth($str) <= $maxLength)
            return $str;
 
        $toated = "...";
        $tLength = mb_strwidth($toated);
        $maxLength = $maxLength - $tLength;
 
        $text = highlight($str, $this->highlights, '<\0>');
        $text = '< >'.$text;
        preg_match_all('/(<[^>]+>)([^<>]*)/', $text, $matches);
        foreach($matches[1] as $i=>$v){
            $matches[1][$i] = str_replace(array('<','>'),'',$v);
        }
        foreach($this->highlights as $i=>$v){
            foreach($matches[1] as $e=>$match){
                if(preg_match('/'.$v . '/i', $match) && !$hitkey[$i]){
                    $hitkey[$i] = $e;
                }
            }
        }
 
 
        if(!$hitkey){
            $tt = mb_strcut($matches[2][0], 0, $maxLength, _CHARSET);
            if(mb_strwidth($matches[2][0]) > $maxLength)
                $tt .= $toated;
        }elseif($hitkey[1]){
            sort($hitkey);
            foreach($hitkey as $keyval){
                $hitWordArray[] = $matches[1][$keyval];
            }
 
            $list[0] = array("qlen"=>0,"q"=>'');
            $trimLength = intval(($maxLength - mb_strwidth(join("",$hitWordArray))) / (count($hitWordArray) +1));
 
            $left = $str;
            foreach($hitWordArray as $i=>$hitWord){
                $tempArray = ($hitWord)? explode($hitWord, $left, 2): array($left, '');
                $preStr = ($hitWord)? $this->splitLastStr($tempArray[0], 5): array($left, '');
 
                $left = $preStr[1].$hitWord.$tempArray[1];
 
                $list[$i]['str'] = $preStr[0];
                $list[$i]['len'] = mb_strwidth($preStr[0]);
 
                $tempTrimLen = $trimLength + $list[$i]['qlen'];
 
                if($list[$i]['len'] < $tempTrimLen){
                    $list[$i]['trimlen'] = 0;
                    $addsum += $tempTrimLen - $list[$i]['len'];
                }else{
                    $list[$i]['trimlen'] = $list[$i]['len'] - $tempTrimLen;
                }
 
                if(!$hitWord) break;
                $list[$i+1]['q'] = $hitWord;
                $list[$i+1]['qlen'] = mb_strwidth($hitWord);
            }
 
            foreach($list as $i=>$v){
                if($list[$i]['trimlen'] && ($addsum > 0)){
                    $list[$i]['trimlen'] = min($v['trimlen'], $addsum);
                    $addsum -= $v['trimlen'];
                    $list[$i]['trimlen'] = $trimLength + $v['trimlen'] + $v['qlen'];
                }elseif($v['trimlen']){
                    $list[$i]['trimlen'] = $trimLength + $v['qlen'];
                }else{
                    $list[$i]['trimlen'] = $v['len'];
                }
            }
 
            $tt = mb_strcut(
                 $list[0]['str'],
                 $list[0]['len'] - $list[0]['trimlen'],
                 $list[0]['trimlen'] + 2,
                 _CHARSET);
            if($list[0]['len'] > $list[0]['trimlen'])
                $tt = $toated.$tt;
 
            foreach($list as $i=>$v){
                $tt .= mb_strcut($v['str'], 0, $v['trimlen'], _CHARSET);
                if($v['len'] > $v['trimlen'])
                    $tt .= $toated;
            }
        }else{
            $hitWord = $this->highlights[0];
            $keyLength = mb_strwidth($hitWord);
 
            $splitStr = preg_quote($hitWord);
            list($preStr, $hStr) = preg_split("/$splitStr/i",$str,2);
 
            $preStrLength = mb_strwidth($preStr);
            $hStrLength = mb_strwidth($hStr);
            $halfLength = intval(($maxLength - $keyLength) / 2);
 
            $hTrimLength = $preTrimLength = $halfLength;
            $minLength = min($preStrLength, $hStrLength, $halfLength);
            if($preStrLength == $minLength){
                $hTrimLength = $maxLength - $keyLength - $preStrLength;
                $preTrimLength = $preStrLength;
            }
            if($hStrLength == $minLength){
                $preTrimLength = $maxLength - $keyLength - $hStrLength;
                $hTrimLength = $hStrLength;
            }
 
            $tt = mb_strcut($preStr, $preStrLength - $preTrimLength, $preStrLength, _CHARSET);
            $tt .= $matches[1][1];
            $tt .= mb_strcut($hStr, 0, $hTrimLength,_CHARSET);
 
            if($preTrimLength < $preStrLength)
                $tt = $toated . $tt;
            if($hTrimLength < $hStrLength)
                $tt .= $toated;
 
        }
        return $tt;
    }
}
