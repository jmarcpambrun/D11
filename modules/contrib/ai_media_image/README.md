# AI Media Image
Enables AI image generation within the Media interface. 

## Dependencies
- [AI](https://www.drupal.org/project/ai)
- An AI provider module with *Text To Image* capabilities
- [Media Library](https://www.drupal.org/docs/core-modules-and-themes/core-modules/media-library-module/overview)

## Installation
Install as usual: https://www.drupal.org/docs/user_guide/en/extend-module-install.html

## Configuration
Ensure your AI and provider modules are configured with a default *Text To Image* model.

## How to use
Visit `/media/add/image`. There is a new dropdown for *Image Source*. Select *Generate Image with AI*, fill in a prompt, and select your model parameters. Click the *Generate Image* button.

Alternatively, visit the content edit screen for which you have configured a *Media Image* field. Click the *Add media* button and proceed as above.