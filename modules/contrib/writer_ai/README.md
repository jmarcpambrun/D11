# Writer AI Provider

This module provides integration with Writer.com's AI models for the Drupal AI module.

## Requirements

- Drupal 10 or 11
- AI module
- Key module
- A valid Writer.com API key

## Installation

1. Enable the module: `drush en writer_ai`
2. Configure your Writer.com API key using the Key module
3. Go to `/admin/config/ai/providers/writer_ai` to configure the provider
4. Set Writer AI as your default provider at `/admin/config/ai/settings`

## Supported Models

This module supports all Writer.com chat models, including:
- Palmyra X5
- Palmyra X4
- Palmyra X 003 Instruct
- Palmyra Med
- Palmyra Fin
- Palmyra Creative

## Vision Support

The module includes support for Writer.com's vision capabilities using the Palmyra Vision model. This enables:
- Image analysis and description
- Alt text generation for images
- Visual content understanding
- Multi-image comparison

Vision functionality is automatically used when images are included in chat messages and works seamlessly with modules like AI Image Alt Text.

## Configuration

The module supports standard AI module configuration options:
- Model selection
- Max tokens (1-4096)
- Temperature (0-2)
- Top P (0-1)

## Usage

Once configured, Writer AI can be used with any AI-enabled module such as:
- AI CKEditor
- AI Image Alt Text (with automatic vision support)
- Custom implementations using the AI module API

The provider automatically detects when images are included in chat messages and uses Writer.com's vision API for analysis.

## API Documentation

For more information about Writer.com's API, visit: https://dev.writer.com/