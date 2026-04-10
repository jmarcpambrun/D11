### About this Module

Build quizzes using the Webform module and its native flow.
With native Twig or decoupled UI.
Use the JSON:API module and Webform REST module to work as usual with Webforms via decoupled schema.

Provides elements: checkbox, radios, text field, date, select, image select.

Provides "quiz" and "survey" modes.
Quiz - is like "all or nothing," the answer must be strictly correct to get a score.
Survey - any answer can be correct and provide its own score.

### Installing the Webform Quiz Module

1. Copy/upload the webform_quiz module to the modules directory of your Drupal installation.

2. Enable the 'Webform Quiz' module and desired sub-modules in 'Extend'. 
   (/admin/modules)

3. Build a new quiz (/admin/structure/webform), click the "Add quiz" button.

### v3 Updates

The main goal is to have a new "survey" mode where options of answers (checkboxes, radios, etc.) have their own score. This means options can work like before with correct answer mode, or can be switched to a mode where there is no correct/wrong answer, but different scores are given for each answer. Single option elements like textboxes will still work the old way—"all or nothing."

- Survey mode: checkboxes, radios, and other options have their own score for each answer.
- Results page shows scores per section.
- PDF generation for visitors to download results.
- Code improvements and bug fixes.
- Fixed "Notice: Undefined index: passing_score" and "Undefined index: passing_score_message."
- Made "Number of points" a common field for all element types.
- Removed annoying bug with extra empty element being added.

There are breaking changes, but no automatic migration is not planned. For manual migration, the admin will have to visit each Quiz of v2 they had, fill in scores per Element again, and save.

### v2 Updates

v2 was developed for www.codpa.org.ua

- Drupal 8.8+ support.
- Checkbox support fix.
  (https://www.drupal.org/project/webform_quiz/issues/3225190)
- New elements: Date, Select, TextField, ImageSelect (as submodule), WebformQuizWizardPage.
- Lots of refactoring and bug fixes.
- All elements now support multiple values as correct answers. TextField supports multiple answers separated by ';;'. Date now has no UI for multiple answers.
- WebformQuizWizardPage is for wrapping up each question into a separate step/page and applying CSS styling.


