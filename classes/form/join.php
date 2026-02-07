<?php
// This file is part of mod_kahoodle plugin
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_kahoodle\form;

use mod_kahoodle\constants;
use mod_kahoodle\local\entities\round;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for joining a round as a participant
 *
 * @package    mod_kahoodle
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join extends \moodleform {
    /**
     * Get the round entity from custom data
     *
     * @return round
     * @throws \coding_exception
     */
    public function get_round(): round {
        if (!isset($this->_customdata['round']) || !($this->_customdata['round'] instanceof round)) {
            throw new \coding_exception('The join form requires a round entity in custom data');
        }
        return $this->_customdata['round'];
    }

    /**
     * Create an inline identity image element for use in form groups.
     *
     * @param string $url The image URL
     * @return \HTML_QuickForm_element
     */
    protected function create_identity_img(string $url): \HTML_QuickForm_element {
        $imgattrs = 'class="mod_kahoodle-identity-img rounded-circle mr-2" width="35" height="35"';
        return $this->_form->createElement(
            'html',
            '<img src="' . s($url) . '" ' . $imgattrs . ' alt="">'
        );
    }

    /**
     * Add a small muted identity caption to the form.
     *
     * @param string $stringkey Language string key from mod_kahoodle
     */
    protected function add_identity_caption(string $stringkey): void {
        $this->_form->addElement(
            'html',
            '<small class="text-muted d-block mb-2">' . get_string($stringkey, 'mod_kahoodle') . '</small>'
        );
    }

    /**
     * Create form elements showing the current user's profile picture and full name.
     *
     * @return \HTML_QuickForm_element[]
     */
    protected function create_realname_elements(): array {
        global $USER, $PAGE;
        $userpicture = new \user_picture($USER);
        $userpicture->size = 35;
        $profilepicurl = $userpicture->get_url($PAGE)->out(false);
        return [
            $this->create_identity_img($profilepicurl),
            $this->_form->createElement('static', '', '', s(fullname($USER))),
        ];
    }

    /**
     * Form definition
     */
    protected function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $round = $this->get_round();
        $context = $round->get_context();

        $mform->addElement('hidden', 'id', $round->get_cm()->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'join');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('html', '<p>' . get_string('landing_participant_message', 'mod_kahoodle') . '</p>');

        $kahoodle = $round->get_kahoodle();
        $identitymode = (int)($kahoodle->identitymode ?? constants::DEFAULT_IDENTITY_MODE);
        $maxlen = constants::DISPLAYNAME_MAXLENGTH;

        if ($identitymode === constants::IDENTITYMODE_OPTIONAL) {
            $avatarurl = $OUTPUT->image_url('useavatar', 'mod_kahoodle')->out(false);

            // Option 1: real name + profile picture.
            $group1 = [
                $mform->createElement('radio', 'identitychoice', '', '', 'realname'),
                ...$this->create_realname_elements(),
            ];
            $mform->addGroup(
                $group1,
                'identity_realname_grp',
                get_string('joinas', 'mod_kahoodle'),
                ' ',
                false
            );
            $mform->setDefault('identitychoice', 'realname');

            // Option 2: nickname + random avatar.
            $group2 = [];
            $group2[] = $mform->createElement('radio', 'identitychoice', '', '', 'alias');
            $group2[] = $this->create_identity_img($avatarurl);
            $group2[] = $mform->createElement('text', 'displayname', '', [
                'maxlength' => $maxlen,
                'size' => $maxlen,
                'placeholder' => get_string('participantdisplayname_form', 'mod_kahoodle'),
            ]);
            $mform->addGroup($group2, 'identity_alias_grp', '', ' ', false);
            $mform->setType('displayname', PARAM_TEXT);
            $mform->disabledIf('displayname', 'identitychoice', 'eq', 'realname');
            $this->add_identity_caption('identitycaption_alias');
        } else if ($identitymode === constants::IDENTITYMODE_REALNAME) {
            // REALNAME: show static "Join as" with profile picture and real name.
            $mform->addGroup(
                $this->create_realname_elements(),
                'identity_realname_grp',
                get_string('joinas', 'mod_kahoodle'),
                ' ',
                false
            );
        } else {
            // ALIAS or ANONYMOUS: always show display name field.
            $mform->addElement(
                'text',
                'displayname',
                get_string('joinas', 'mod_kahoodle'),
                ['maxlength' => $maxlen, 'size' => $maxlen,
                 'placeholder' => get_string('participantdisplayname_form', 'mod_kahoodle')],
            );
            $mform->setType('displayname', PARAM_TEXT);
            $mform->addRule('displayname', null, 'required', null, 'client');
            $mform->addRule('displayname', get_string('maximumchars', '', $maxlen), 'maxlength', $maxlen, 'client');
            $captionkey = $identitymode === constants::IDENTITYMODE_ANONYMOUS
                ? 'identitycaption_anonymous' : 'identitycaption_alias';
            $this->add_identity_caption($captionkey);
        }

        $buttonlabel = has_capability('mod/kahoodle:facilitate', $context)
            ? get_string('join_as_participant', 'mod_kahoodle')
            : get_string('join', 'mod_kahoodle');
        $this->add_action_buttons(false, $buttonlabel);
    }

    /**
     * Return submitted data if properly submitted, with displayname normalised by identity mode.
     *
     * For realname mode, displayname is always null. For optional mode, displayname is
     * null when the user chose the realname radio. For alias/anonymous modes, the
     * submitted displayname is returned as-is.
     *
     * @return \stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data === null) {
            return null;
        }

        $kahoodle = $this->get_round()->get_kahoodle();
        $identitymode = (int)($kahoodle->identitymode ?? constants::DEFAULT_IDENTITY_MODE);

        if ($identitymode === constants::IDENTITYMODE_REALNAME) {
            $data->displayname = null;
        } else if (
            $identitymode === constants::IDENTITYMODE_OPTIONAL
                && ($data->identitychoice ?? '') !== 'alias'
        ) {
            $data->displayname = null;
        }

        return $data;
    }

    /**
     * Server-side validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $round = $this->get_round();
        $kahoodle = $round->get_kahoodle();
        $identitymode = (int)($kahoodle->identitymode ?? constants::DEFAULT_IDENTITY_MODE);
        $maxlen = constants::DISPLAYNAME_MAXLENGTH;

        if (
            $identitymode === constants::IDENTITYMODE_OPTIONAL
                && ($data['identitychoice'] ?? '') === 'alias'
        ) {
            $displayname = trim($data['displayname'] ?? '');
            if ($displayname === '') {
                $errors['identity_alias_grp'] = get_string('required');
            } else if (\core_text::strlen($displayname) > $maxlen) {
                $errors['identity_alias_grp'] = get_string('maximumchars', '', $maxlen);
            }
        }

        return $errors;
    }
}
