<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script lets the user create or edit question tests for a question.
 *
 * @copyright  2012 the Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/questiontestform.php');
require_once(dirname(__FILE__) . '/stack/questiontest.php');


// Get the parameters from the URL.
$questionid = required_param('questionid', PARAM_INT);
$seed = optional_param('seed', null, PARAM_INT);
$testcase = optional_param('testcase', null, PARAM_INT);

// Load the necessary data.
$questiondata = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
$question = question_bank::load_question($questionid);
$context = $question->get_context();
if ($testcase) {
    $qtest = question_bank::get_qtype('stack')->load_question_test($questionid, $testcase);
}

// Check permissions.
require_login();
question_require_capability_on($questiondata, 'edit');

// Initialise $PAGE.
$urlparams = array('questionid' => $question->id);
if (!is_null($seed)) {
    $urlparams['seed'] = $seed;
}
$backurl = new moodle_url('/question/type/stack/questiontestrun.php', $urlparams);
if (!is_null($testcase)) {
    $urlparams['testcase'] = $testcase;
}
$PAGE->set_url('/question/type/stack/questiontestedit.php', $urlparams);
$PAGE->set_context($context);
// TODO fix page layout and navigation.

if (!is_null($testcase)) {
    $title = get_string('editingtestcase', 'qtype_stack',
            array('no' => $testcase, 'question' => format_string($question->name)));
    $submitlabel = get_string('savechanges');
} else {
    $title = get_string('addingatestcase', 'qtype_stack', format_string($question->name));
    $submitlabel = get_string('createtestcase', 'qtype_stack');
}

// Create the editing form.
$mform = new qtype_stack_question_test_form($PAGE->url,
        array('submitlabel' => $submitlabel, 'question' => $question));

// Send current data to the form.
if ($testcase) {
    $currentdata = new stdClass();

    foreach ($qtest->inputs as $name => $value) {
        $currentdata->{$name} = $value;
    }

    foreach ($qtest->expectedresults as $prtname => $expected) {
        $currentdata->{$prtname . 'score'}      = $expected->score;
        $currentdata->{$prtname . 'penalty'}    = $expected->penalty;
        $currentdata->{$prtname . 'answernote'} = $expected->answernote[0];
    }

    $mform->set_data($currentdata);
}

// Process the form.
if ($mform->is_cancelled()) {
    unset($urlparams['testcase']);
    redirect($backurl);

} else if ($data = $mform->get_data()) {
    // Process form submission.
    $inputs = array();
    foreach ($question->inputs as $inputname => $notused) {
        $inputs[$inputname] = $data->$inputname;
    }
    $qtest = new stack_question_test($inputs);

    foreach ($question->prts as $prtname => $notused) {
        $qtest->add_expected_result($prtname, new stack_potentialresponse_tree_state(
                '', array(), array($data->{$prtname . 'answernote'}), true,
                $data->{$prtname . 'score'}, $data->{$prtname . 'penalty'}));
    }

    question_bank::get_qtype('stack')->save_question_test($questionid, $qtest, $testcase);
    redirect($backurl);
}

// Create the question usage we will use.
$quba = question_engine::make_questions_usage_by_activity('qtype_stack', $context);
$quba->set_preferred_behaviour('adaptive');
$slot = $quba->add_question($question, $question->defaultmark);
$quba->start_question($slot, $seed);

// Prepare the display options.
$options = new question_display_options();
$options->readonly = true;
$options->flags = question_display_options::HIDDEN;
$options->suppressruntestslink = true;

// Display the page.
$PAGE->set_title($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Show the question read-only.
echo $quba->render_question($slot, $options);

// Display the question variables.
echo $OUTPUT->heading(get_string('questionvariables', 'qtype_stack'), 3);
echo html_writer::start_tag('div', array('class' => 'questionvariables'));
foreach ($question->get_all_question_vars() as $key => $value) {
    echo  html_writer::tag('p', s($key) . ' = ' . s($value));
}
echo html_writer::end_tag('div');

// Display the question text.
// We need this as well as the rendered view above so that teachers can see the names of variables used.
// This helps when writing question tests using those variables to reflect randomization.
echo $OUTPUT->heading(get_string('questiontext', 'qtype_stack'), 3);
echo html_writer::tag('pre', $question->questiontext, array('class' => 'questiontext'));

// Show the form.
$mform->display();
echo $OUTPUT->footer();