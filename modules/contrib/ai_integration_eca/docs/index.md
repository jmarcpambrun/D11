# AI Integration - ECA

Artificial Intelligence integration for Event-Condition-Action module, combining
the unified framework and the power of Drupal to perform various AI-related
operations.

## Dependencies

- Drupal ^10.3 || ^11
- [AI module](https://drupal.org/project/ai)
- [ECA module](https://drupal.org/project/eca)

## Getting started

1. Enable the module
2. Open an ECA model
3. Use an operation type (`Chat`, `Moderation`, `TextToSpeech` etc.) as action

## Documentation

This project uses MkDocs for documentation: you can see the current
documentation at [https://project.pages.drupalcode.org/ai_integration_eca/](https://project.pages.drupalcode.org/ai_integration_eca/).
The documentation source files are located in the `docs/` directory, and to
build your own local version of the documentation please follow these steps:

1. Install MkDocs: `pip install mkdocs mkdocs-material`
2. Run `mkdocs serve` in the project root
3. Open `http://localhost:8000` in your browser
