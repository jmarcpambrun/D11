# Quiz

## Table of Contents
* Installation
* About
* Features
* Question types included
* Configuration
  * How to create a quiz
* Similar modules
  * Modules that extend Quiz
* Support
* Help out
* Credits

## Installation

Install the UI Patterns Setting module as you would normally install a
contributed Drupal module. Visit https://www.drupal.org/node/1897420.

## About

The Quiz module lets you create graded assessments in Drupal. A Quiz is given as
a series of questions. Answers are then stored in the database. Scores and
results are displayed during or after the quiz. Administrators can provide
automatic or manual feedback. See all the features below! This module can be
used as

*   an object in a larger LMS, or a supplemental classroom activity
*   a standalone activity (audio, video, rich text in questions/answers)
*   a self-learning program, using adaptive mode with multiple answer tries
*   a training program, with multiple improving attempts


## Features

*   Extensive Views, Rules integration through Entity API
*   Integration with H5P making 20+
    [content types](http://h5p.org/content-types-and-applications) available
*   OO Question API
*   Very configurable feedback times and options
*   Pre-attempt questionnaires (through Field API)
*   Views and Views Bulk Operations for managing questions/results
*   Drag and drop ordering of questions/answers/pages
*   Configurable questions per page
*   Devel generate support (dummy Quiz/Question/Result data)
*   Question randomization, from per-Quiz pool or taxonomy category
*   Certainty-based marking
*   Multiple attempts per user
*   Lots of unit test coverage
*   Adaptive mode and feedback
*   Build on last attempt mode
*   Timed quizzes
*   Question reuse across multiple Quizzes
*   Robust Quiz/Question versioning
*   AJAX quiz taking
*   And many more...

## Question types included

*   Extensive Views
*   Rules integration through Entity API
*   OO Question API
*   Very configurable feedback times and options
*   Pre-attempt questionnaires (through Field API)
*   Views and Views Bulk Operations for managing questions/results
*   Drag and drop ordering of questions/answers/pages
*   Configurable questions per page
*   Devel generate support (dummy Quiz/Question/Result data)
*   Question randomization, from per-Quiz pool or taxonomy category
*   Certainty-based marking
*   Multiple attempts per user
*   Lots of unit test coverage
*   Adaptive mode and feedback
*   Build on last attempt mode
*   Timed quizzes
*   Question reuse across multiple Quizzes
*   Robust Quiz/Question versioning
*   AJAX quiz taking
*   And many more...

## Configuration

### How to create a quiz

1. Create a basic quiz by going to /admin/quiz/quizzes and clicking
   Add quiz. You will have  the opportunity to set many options here.
2. Go to the Questions tab and add questions to the quiz. Here you can create
   a new question, or use the question bank to add a previously used question.
3. After adding questions, click the "Take" tab to take the Quiz!

## Similar modules

*   [H5P - HTML5 learning objects](https://www.drupal.org/project/h5p)
*   [Course](https://www.drupal.org/project/course) - put multiple quizzes together
*   [Certificate](https://www.drupal.org/project/certificate) - award a certificate after passing a Course/Quiz

### Modules that extend Quiz

* [Charts](http://drupal.org/project/charts) - used by Quiz stats to render some useful data
* [jQuery Countdown](http://hilios.github.io/jQuery.countdown) - provides
  jQuery timer for timed quizzes. Install manually or by composer.
  ````
  "type": "package",
  "package": {
      "name": "hilios/jquery-countdown",
      "version": "2.2.0",
      "type": "drupal-library",
      "dist": {
          "url": "https://github.com/hilios/jQuery.countdown/archive/refs/tags/2.2.0.zip",
          "type": "zip"
      }
  }
  ````
  and then ``composer require hilios/jquery-countdown``
* [Views Data Export](http://drupal.org/project/views_data_export) - export Quiz results and user answers
* [Webform Quiz Elements](https://www.drupal.org/project/webform_quiz_elements)

## Support

We have a big community supporting Quiz, and it's getting bigger! Let's make
this the best assessment engine, ever. [IRC](https://drupal.org/irc),
in #drupal-course (for Quiz, Course, Certificate module support)
[IRC](https://drupal.org/irc), in #drupal-edu (general edu talk),
[Drupal groups](https://groups.drupal.org/quiz),
[The issue queue](https://www.drupal.org/project/issues/quiz)

## Help out

Please continue to help out with cleaning up the issue queue!
https://drupal.org/quiz/2280951 Have a feature request? Please open an issue in
the issue queue!

## Credits

Many users have contributed lots of feature requests and bug reports. Previous
maintainers also deserve a lot of credit! Join the Quiz group at
http://groups.drupal.org/quiz to get involved! **Quiz is currently being
sponsored by:** djdevin@[DLC Solutions](http://www.dlc-solutions.com)/
[EthosCE](http://www.ethosce.com) for the 7.x-5.x branch **Previous sponsors**
[The e-learning company Amendor](http://amendor.com),
[The Norwegian Centre for ICT in Education](http://iktsenteret.no/english),
[Norwegian Centre for Integrated Care and Telemedicine](http://telemed.no/)
