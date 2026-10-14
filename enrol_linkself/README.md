# Enrolment via link #

Enrolment plugin that lets users enrol themselves in a course through a secret link or QR code.

Course teachers add the method "Enrolment via link" to a course and share the generated link or
print the QR code. Anyone who opens the link logs in, sees a confirmation page with the course name
and enrols by confirming. The link never enrols anybody on its own, and nobody can be enrolled by
someone else through this method.

Enrolment can be closed at any time. Users who are already enrolled keep their access to the course.

## Features ##

* One secret link per course (256-bit random token). The link contains no course id.
* QR code for the link, ready to print or show on a slide.
* Confirmation step: users must log in and actively confirm the enrolment.
* Close and reopen enrolment without affecting existing enrolments.
* Create a new link: the previous link and all printed QR codes become invalid immediately.
* Optional enrolment window (start and end date) and maximum number of participants.
* Configurable role assigned on enrolment.
* Course backup and restore never copy the token. A restored instance gets a new link and starts closed.

## Requirements ##

* Moodle 5.0 or later (developed for Moodle 5.2).

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/enrol/linkself

On Moodle 5.1 and later with the new directory layout, use `{your/moodle/dirroot}/public/enrol/linkself`.

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Configuration ##

1. Enable the plugin under _Site administration > Plugins > Enrolments > Manage enrol plugins_.
2. In a course, go to _Participants > Enrolment methods_ and add "Enrolment via link".
3. Click the link icon of the method to open the page with the enrolment link and the QR code.
   From there you can close or reopen enrolment and create a new link.

## Capabilities ##

* `enrol/linkself:enrolself` – Enrol oneself via the enrolment link (authenticated user).
* `enrol/linkself:config` – Configure the method and manage the link (manager, editing teacher).
* `enrol/linkself:manage` – Edit enrolments made via this method (manager, editing teacher).
* `enrol/linkself:unenrol` – Unenrol users enrolled via this method (manager, editing teacher).
* `enrol/linkself:unenrolself` – Unenrol oneself from the course (not granted by default).

## Privacy ##

The plugin does not store any personal data. Enrolments are stored in Moodle's core enrolment tables.

## Maturity ##

Alpha. Test the plugin on a non-production site before using it.

## License ##

2026 Guido Brombach

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
