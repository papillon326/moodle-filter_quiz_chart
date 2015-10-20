<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

class filter_quiz_chart extends moodle_text_filter {
    public function setup($page, $context) {
        static $jsinitialised = false;
        
        if ($jsinitialised) {
            return true;
        }
        
        $url = new moodle_url('/lib/yuilib/3.13.0/charts-base/charts-base.js');
        $page->requires->js($url);
        
        $jsinitialised = true;
        
        return true;
    }
    
    public function filter($text, array $options = array()) {
        global $CFG, $DB, $COURSE, $USER;
        $counter = 0;
        
        // shortcut
        if (strpos($text, '[quizchart:') === false){
            return $text;
        }
        //echo '<pre>'; var_dump($text); echo '</pre>';
        
        // get placeholders
        if (preg_match_all('/\[quizchart:([0-9]+)\]/', $text, $matches, PREG_SET_ORDER) === false) return $text;
        //echo '<pre>'; var_dump($matches); echo '</pre>';

        foreach ($matches as $match) {
            $counter++;
            list($link_text, $quiz_cmid) = $match;
            
            $cm = get_coursemodule_from_id('quiz', $quiz_cmid, $COURSE->id);
            if(!$cm) continue;
            
            $quiz = $DB->get_record('quiz', array('id' => $cm->instance));
            if(!$quiz) continue;
            //echo '<pre>'; var_dump($match); var_dump($quiz); echo '</pre>';
            
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
            //echo '<pre>'; var_dump($bands); echo '</pre>';
            
            // See MDL-34589. Using doubles as array keys causes problems in PHP 5.4,
            // hence the explicit cast to int.
            $bands = (int) ceil($bands);
            $bandwidth = $quiz->grade / $bands;
            $bandlabels = array();
            for ($i = 1; $i <= $bands; $i++) {
                $bandlabels[] = quiz_format_grade($quiz, ($i - 1) * $bandwidth) . ' - ' .
                        quiz_format_grade($quiz, $i * $bandwidth);
            }
            //echo '<pre>'; var_dump($bandlabels); echo '</pre>';
            
            // get quiz histogram data
            $participant = quiz_report_grade_bands($bandwidth, $bands, $quiz->id);
            echo '<pre>'; var_dump($participant); echo '</pre>';
            
            // get my grade in the histogram
            $mygrade = quiz_report_grade_bands($bandwidth, $bands, $quiz->id, array($USER->id));
            $mygrade = array_search('1', $mygrade);
            
            // create chart data
            $chart_data = array();
            $participant_max = 0;
            for ($i=0; $i<count($bandlabels); $i++) {
                $chart_data[$i]['bandlabel']   = $bandlabels[$i];
                $chart_data[$i]['participant'] = (int) $participant[$i];
                
                $participant_max = max($participant_max, $participant[$i]);
            }
            
            // set band color
            $colors = array_fill(0, count($bandlabels), '#FF0000');
            if ($mygrade !== false) {
              $colors[$mygrade] = '#FF00FF';
            }
            
            $chart_data = json_encode($chart_data);
            $colors = json_encode($colors);
            $part_max = ceil($participant_max/10)*10;
            
            $lang = new stdClass();
            $lang->participants = get_string('participants');
            $lang->grade = get_string('grade');
            
            $quiz_uri = new moodle_url('/mod/quiz/view.php?id=' . $quiz_cmid);
            
$html =<<< __HTML__
<div name="quizchart_title" style="text-align:center;"><a href="{$quiz_uri}">{$quiz->name}</a></div>
<div id="quizchart-{$quiz_cmid}-{$counter}" style="min-width:500px;width:100%;height:400px;"></div>
<script type="text/javascript">
    var chartData{$quiz_cmid} = {$chart_data};
    var colors{$quiz_cmid} = {$colors};
    YUI().use('charts', function (Y) {
        var myChart = new Y.Chart({
            dataProvider: chartData{$quiz_cmid},
            render: "#quizchart-{$quiz_cmid}-{$counter}",
            categoryKey: 'bandlabel',
            horizontalGridlines: {
                styles: {
                    line: {
                        color: '#dad8c9',
                    }
                }
            },
            axes: {
                participant: {
                    keys: ['participant'],
                    type: 'numeric',
                    position: 'left',
                    title: "{$lang->participants}",
                    maximum: {$part_max},
                    minimum: 0
                },
                bandlabel: {
                    styles: {
                        label: {
                            rotation: -45
                        }
                    },
                    title: '{$lang->grade}'
                }
            },
            seriesCollection: [
                {
                    type: 'column',
                    yAxis: 'participant',
                    yKey: 'participant',
                    styles: {
                        marker: {
                            fill: {
                                color:  colors{$quiz_cmid}
                            },
                            width: 25
                        }
                    }
                }
            ],
            tooltip: {
                markerLabelFunction: function(categoryItem, valueItem, itemIndex, series, seriesIndex)
                {
                    var msg = document.createElement('div');
                    msg.appendChild(document.createTextNode('{$lang->grade}: ' + categoryItem.value));
                    msg.appendChild(document.createElement('br'));
                    msg.appendChild(document.createTextNode('{$lang->participants}: ' + chartData{$quiz_cmid}[itemIndex].participant));
                    return msg;
                }
            }
        });
    });
</script>
__HTML__;
            $text = str_replace($link_text, $html, $text);
            
        }
        
        return $text;
    }
}
