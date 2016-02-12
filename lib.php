<?php

require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_table.php');

require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');

class quiz_chartdata_table extends quiz_attempts_report_table {
    
            public function __construct($quiz, $context, $qmsubselect,
            quiz_overview_options $options, $groupstudents, $students, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-overview-report', $quiz , $context,
                $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    public function build_table() {
        global $DB;
        
        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        
        //parent::build_table();
        
        if ($this->groupstudents) {
            $this->add_average_row(get_string('groupavg', 'grades'), $this->groupstudents);
        }

        if ($this->students) {
            $this->add_average_row(get_string('overallaverage', 'grades'), $this->students);
        }
//var_dump($this);die();
    }

    protected function add_average_row($label, $users) {
        global $DB;

        list($fields, $from, $where, $params) = $this->base_sql($users);
        $record = $DB->get_record_sql("
                SELECT AVG(quiza.sumgrades) AS grade, COUNT(quiza.sumgrades) AS numaveraged
                  FROM $from
                 WHERE $where", $params);
        $record->grade = quiz_rescale_grade($record->grade, $this->quiz, false);

        if ($this->is_downloading()) {
            $namekey = 'lastname';
        } else {
            $namekey = 'fullname';
        }
        $averagerow = array(
            $namekey    => $label,
            'sumgrades' => $this->format_average($record),
            'feedbacktext'=> strip_tags(quiz_report_feedback_for_grade(
                                        $record->grade, $this->quiz->id, $this->context))
        );

        if ($this->options->slotmarks) {
            $dm = new question_engine_data_mapper();
            $qubaids = new qubaid_join($from, 'quiza.uniqueid', $where, $params);
            $avggradebyq = $dm->load_average_marks($qubaids, array_keys($this->questions));

            $averagerow += $this->format_average_grade_for_questions($avggradebyq);
        }

        $this->add_data_keyed($averagerow);
    }

        protected function format_average($record, $question = false) {
        if (is_null($record->grade)) {
            $average = 0;
        } else if ($question) {
            $average = quiz_format_question_grade($this->quiz, $record->grade);
        } else {
            $average = quiz_format_grade($this->quiz, $record->grade);
        }

        return $average;
    }
        
    protected function format_average_grade_for_questions($gradeaverages) {
        $row = array();

        if (!$gradeaverages) {
            $gradeaverages = array();
        }

        foreach ($this->questions as $question) {
            if (isset($gradeaverages[$question->slot]) && $question->maxmark > 0) {
                $record = $gradeaverages[$question->slot];
                $record->grade = quiz_rescale_grade(
                        $record->averagefraction * $question->maxmark, $this->quiz, false);

            } else {
                $record = new stdClass();
                $record->grade = null;
                $record->numaveraged = 0;
            }

            $row['qsgrade' . $question->slot] = $this->format_average($record, true);
        }

        return $row;
    }
}


class quiz_chartdata_report extends quiz_attempts_report {

    public function display($quiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->init('overview', 'quiz_overview_settings_form', $quiz, $cm, $course);
        $options = new quiz_overview_options('overview', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);

        } else {
            $options->process_settings_from_params();
        }

        $this->form->set_data($options->get_initial_form_data());

        if ($options->attempts == self::ALL_WITH) {
            // This option is only available to users who can access all groups in
            // groups mode, so setting allowed to empty (which means all quiz attempts
            // are accessible, is not a security porblem.
            $allowed = array();
        }

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_chartdata_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudents, $students, $questions, $options->get_url());
        //echo '<pre>'; var_dump($table); echo '</pre>';

                $this->course = $course; // Hack to make this available in process_actions.
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $options->get_url());

        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector if we are not downloading.
            if (!$table->is_downloading()) {
                groups_print_activity_menu($cm, $options->get_url());
            }
        }
        
        $hasquestions = quiz_has_questions($quiz->id);
        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $options->attempts == self::ALL_WITH)) {
            // Construct the SQL.
            $fields = $DB->sql_concat('u.id', "'#'", 'COALESCE(quiza.attempt, 0)') .
                    ' AS uniqueid, ';

            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

            // Test to see if there are any regraded attempts to be listed.
            $fields .= ", COALESCE((
                                SELECT MAX(qqr.regraded)
                                  FROM {quiz_overview_regrades} qqr
                                 WHERE qqr.questionusageid = quiza.uniqueid
                          ), -1) AS regraded";
            if ($options->onlyregraded) {
                $where .= " AND COALESCE((
                                    SELECT MAX(qqr.regraded)
                                      FROM {quiz_overview_regrades} qqr
                                     WHERE qqr.questionusageid = quiza.uniqueid
                                ), -1) <> -1";
            }
            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $options->checkboxcolumn) {
                $columns[] = 'checkbox';
                $headers[] = null;
            }

            $this->add_user_columns($table, $columns, $headers);
            $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            $this->add_grade_columns($quiz, $options->usercanseegrades, $columns, $headers, false);

            if (!$table->is_downloading() && has_capability('mod/quiz:regrade', $this->context) &&
                    $this->has_regraded_questions($from, $where, $params)) {
                $columns[] = 'regraded';
                $headers[] = get_string('regrade', 'quiz_overview');
            }

            if ($options->slotmarks) {
                foreach ($questions as $slot => $question) {
                    // Ignore questions of zero length.
                    $columns[] = 'qsgrade' . $slot;
                    $header = get_string('qbrief', 'quiz', $question->number);
                    if (!$table->is_downloading()) {
                        $header .= '<br />';
                    } else {
                        $header .= ' ';
                    }
                    $header .= '/' . quiz_rescale_grade($question->maxmark, $quiz, 'question');
                    $headers[] = $header;
                }
            }

            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $options, false);
            $table->set_attribute('class', 'generaltable generalbox grades');

            $table->out(1, true);
        }
        
        return true;
    }

    protected function has_regraded_questions($from, $where, $params) {
        global $DB;
        return $DB->record_exists_sql("
                SELECT 1
                  FROM {$from}
                  JOIN {quiz_overview_regrades} qor ON qor.questionusageid = quiza.uniqueid
                 WHERE {$where}", $params);
    }

}