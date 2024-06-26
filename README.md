# tool_uploadactivitycompletions

An admin tool to allow import of completion results (as overrides) for any sort of activity using a text delimited file. Uses the name of the section (topic) and name of the activity (mod) to determine the activity to be completed. Skips records where completion isn't enabled.

Access it through *Site Administration > Courses > Upload Activity Completions* or by typing in `/admin/tool/uploadactivitycompletions` after your moodle path.

Use this tool to import manual user completions against activities within courses. Users will be manually enrolled as a student if required. Use a standard CSV file that contains the courses, users and activities to import completions against.

Completions are performed on behalf of the student by the user performing the import.

* You need to specify the course (short) name or the course idnumber. The course needs to have completion enabled.
* You need to specify the user name or idnumber.
* You need to specify the section (topic) name that contains the activity to complete (case insensitive). If your activity is in the top section, write '0' as your topic name.
* You need to specify the name of the activity to complete (e.g. the page, quiz, scorm, url, etc). This activity needs to have completion enabled.
* You can optionally specify a `completiondate` to set the completion date of the activity. The `completiondate` should be in Unix timestamp format. If not specified, the current date will be used.

The possible csv column names are:

```csv
coursename, courseidnumber, username, useridnumber, sectionname, activityname, completiondate
```

## Example csv

```csv
coursename, username, sectionname, activityname, courseidnumber, useridnumber, completiondate
digipolitech, lara.croft88, "Course Material", "Scorm Package",, , 1706873580
digipolitech, lara.croft88, "Course Material", "Reflection",, , 1706873580
digipolitech, gordon.freeman3, "Course Material", "Scorm Package",, , 1706873580
```


If you specify both coursename/idnumber or username/idnumber it the idnumber will take precedence.

## Installation

Install via the moodle plugin installer, or by git

```sh
gh repo clone tristdac/moodle-tool_uploadactivitycompletions
```

## Usage

Open up the tool and follow the instructions. Upload your CSV, specify the matching columns (or select None), continue and wait ...

There is also a command line option:

```sh
sudo -u www-data /usr/bin/php admin/tool/uploadactivitycompletions/cli/uploadactivitycompletions.php
--source=./completions.csv
```

## Disclaimer
This update was written for and tested in Moodle v4.1.4 only. Use it at your own risk. No liability is assumed if it breaks something or doesn't work as expected.

## Acknowledgements

Based heavily on the Upload Page Results plugin by Lush Online [uploadpageresults](https://github.com/lushonline/moodle-tool_uploadpageresults)
Also on work by Frédéric Massart and Piers harding on the core [admin\tool\uploadcourse](https://github.com/moodle/moodle/tree/master/admin/tool/uploadcourse)

## Licence

GPL3

## Update
This update to the `README.md` explains the new `completiondate` functionality, including how to use it in the CSV file and the expected format.
