<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once(__DIR__.'/lib.php');

class filter_quiz_chart2 extends moodle_text_filter {
    public function setup($page, $context) {
        static $jsinitialised = false;
        
        if ($jsinitialised) {
            return true;
        }
        
        $url = new moodle_url('/filter/quiz_chart2/d3js/d3.min.js');
        $page->requires->js($url);
        
        $url = new moodle_url('/filter/quiz_chart2/drawhisto.js');
        $page->requires->js($url);
        
        $jsinitialised = true;
        
        return true;
    }
    
    public function filter($text, array $options = array()) {
        global $CFG, $DB, $PAGE, $COURSE, $USER;
        $counter = 0;
        
        // shortcut
        if (strpos($text, '[quizchart2:') === false){
            return $text;
        }
        
        // get placeholders
        if (preg_match_all('/\[quizchart2:([0-9]+)\]/', $text, $matches, PREG_SET_ORDER) === false) return $text;

        $all_chart_data = array();
        foreach ($matches as $match) {
            list($link_text, $quiz_cmid) = $match;
            
            $cm = get_coursemodule_from_id('quiz', $quiz_cmid, $COURSE->id);
            if(!$cm) continue;
            
            $quiz = $DB->get_record('quiz', array('id' => $cm->instance));
            if(!$quiz) continue;
            
            $report = new quiz_chartdata_report();
            $report->display($quiz, $cm, $COURSE);
            
            // Pick a sensible number of bands depending on quiz maximum grade.
            $bands = $quiz->grade;
            while ($bands > 20 || $bands <= 10) {
                if ($bands > 50) {
                    $bands /= 5;
                } else if ($bands > 20) {
                    $bands /= 2;
                }
                if ($bands < 4) {
                    $bands *= 5;
                } else if ($bands <= 10) {
                    $bands *= 2;
                }
            }
            
            // See MDL-34589. Using doubles as array keys causes problems in PHP 5.4,
            // hence the explicit cast to int.
            $bands = (int) ceil($bands);
            $bandwidth = $quiz->grade / $bands;
            $bandlabels = array();
            for ($i = 1; $i <= $bands; $i++) {
                $bandlabels[] = quiz_format_grade($quiz, ($i - 1) * $bandwidth) . ' - ' .
                        quiz_format_grade($quiz, $i * $bandwidth);
            }
            
            // get quiz histogram data
            $participant = quiz_report_grade_bands($bandwidth, $bands, $quiz->id);
            
            // get my grade in the histogram
            $mygrade = quiz_report_grade_bands($bandwidth, $bands, $quiz->id, array($USER->id));
            $mygrade = array_search('1', $mygrade);
            
            // create chart data
            $chart_data = array();
            $participant_max = 0;
            for ($i=0; $i<count($bandlabels); $i++) {
                $chart_data[$i] = array($bandlabels[$i] => (int) $participant[$i]);
                
                $participant_max = (int) max($participant_max, $participant[$i]);
            }
            $chartData = new stdClass();
            $chartData->bandlabels = $bandlabels;
            $chartData->participants = $participant;
            
            $part_max = ceil($participant_max/10)*10;
            
            $lang = new stdClass();
            $lang->participants = get_string('participants');
            $lang->grade = get_string('grade');
            
            $all_chart_data[$counter] = array('data'=>$chartData,
                                              'mygrade'=>$mygrade,
                                              'maxVal'=>$part_max,
                                              'lang'=>$lang,
                                              'regularcolor'=>'#FF0000',  // TODO: change configurable
                                              'specialcolor'=>'#FF00FF'   // TODO: change configurable
                                              );
            
            $quiz_uri = new moodle_url('/mod/quiz/view.php?id=' . $quiz_cmid);
            
$html =<<< __HTML__
<div name="quizchart_title" style="text-align:center;"><a href="{$quiz_uri}">{$quiz->name}</a></div>
<svg id="quizchart-{$counter}" style="min-width:500px;width:100%;height:400px;"></svg>
__HTML__;
            $text = str_replace($link_text, $html, $text);

            $counter++;
        }

$all_chart_data = json_encode($all_chart_data, JSON_NUMERIC_CHECK);
$html =<<< __HTML__
<script type="text/javascript">
//<![CDATA[
    var quizChartData = {$all_chart_data};
//]]>
</script>
<style>
    .axis path,
    .axis line {
        fill: none;
        stroke: black;
    }
    .axis text {
        font-size: 11px;
        font-family: sans-serif;
    }
    
    .x-axis-label {
        font-size: 11px;
        font-family: sans-serif;
        text-anchor: middle;
    }
    
    .tick line {
      opacity: 0.2;
    }
    
</style>
__HTML__;
        
        $text .= $html;
        
        return $text;
        
    }
}
