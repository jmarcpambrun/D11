# Multi-value form element

Provides a multi-value form element that wraps any number of form elements.

Example:
```php
$form['contacts'] = [
  '#type' => 'multivalue',
  '#title' => $this->t('Contacts'),
  'name' => [
    '#type' => 'textfield',
    '#title' => $this->t('Name'),
  ],
  'mail' => [
    '#type' => 'email',
    '#title' => $this->t('E-mail'),
  ],
];
```

For extended documentation, see the form element class documentation.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/multivalue_form_element/tags).
