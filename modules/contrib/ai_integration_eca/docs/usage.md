# Usage

Enable the module, open an ECA model, and add one of the AI actions below. Each
action selects a provider/model for its operation type. Most actions take their
input from a **Token input** field and write their result into a token
(`token_result`) for later steps. The Chat and Text to Image actions take their
prompt from a dedicated field instead, where all ECA tokens are replaced
automatically.

**Token input** accepts any of:

- the name of an ECA token, bare (`my_input`) the way ECA's other token fields
  are filled in, or wrapped in brackets (`[my_input]`) — both work, and a
  chained name may be written with dots or colons (`node.body`, `node:body`);
- a Drupal token such as `[site:name]` or `[node:title]`;
- a value typed straight in, in which case any tokens it contains are replaced
  first, so `Summarize: [node:body]` works too.

A name is looked up as an ECA token first, and only a name that no token
answers to falls through to replacement. That keeps a token holding a list or
structured data intact, rather than flattening it into a string.

**Token result** is different: it *names* the token the action writes to, so it
takes a bare name and nothing else.

## Available actions

| Action | Operation type | Input | Result token |
|--------|----------------|-------|--------------|
| Chat | `chat` | prompt text (+ optional system prompt, schema, files) | response string |
| Moderation | `moderation` | text | DTO `{flagged, information}` |
| Embedding | `embeddings` | text | embedding DTO |
| Text to Speech | `text_to_speech` | text | file URL |
| Speech to Text | `speech_to_text` | audio file | transcribed text |
| Text to Image | `text_to_image` | prompt text | list of file references |
| Image to Image | `image_to_image` | image file (+ optional prompt, mask) | list of file references |
| Image to Video | `image_to_video` | image file | list of file references |
| Speech to Speech | `speech_to_speech` | audio file | list of file references |
| Audio to Audio | `audio_to_audio` | audio file | list of file references |
| Image Classification | `image_classification` | image file (+ optional labels) | associative array `{label: confidence}` |
| Object Detection | `object_detection` | image file | list of `{label, confidence, box}` |
| Translate Text | `translate_text` | text (+ optional source, target language) | translated string |

## File input and output

The file-based actions (the image/audio/video ones above) expose extra options:

- **Input source** — how the file input is read: *Managed file ID*, *File path
  or URI*, or *Raw binary*.
- **Output as** — how each generated file is stored in the result token: *Managed
  file ID* (default), *File path* (an unmanaged file written to the destination),
  or *Raw binary*. The result token is **always a list**, even when the model
  returns a single file.
- **Destination directory** — the stream-wrapper directory for written files,
  e.g. `public://ai`. Defaults to `public://`. Used by the managed-file and
  file-path output modes.

### What counts as a valid file input

The input token is ordinary flow data, so its contents are decided by the model
you build rather than by the action. *File path or URI* therefore accepts only
URIs in the site's own file storage:

- The scheme has to be one the site accepts: `public://`, `private://` and
  `temporary://` out of the box. A plain filesystem path, absolute or relative,
  is not one of them, and neither is any other scheme until you add it.
- A URI has to stay inside its own wrapper — no `../` segments — and point at a
  file that exists.

An input that does not qualify is not read, and the action produces no result,
in keeping with how these actions handle every other failure; the reason is
written to the log. Prefer *Managed file ID* where you can: uploads have already
passed core's file validators, and the action never sees a free-form path.

The **Filename** is token-replaced as well, so it is reduced to a bare filename
and always written inside the destination directory.

### The Image to Image mask

The Image to Image action has an optional **Mask** field naming a second image
that tells the model which area to change. It takes the same three shapes as
*Token input* — a token name, a Drupal token, or a value typed straight in —
and is read with the same *Input source* as the main image, so both have to be
supplied the same way.

An unreadable mask is skipped and the request goes out without it, since the
mask is optional; an unreadable main image stops the action.

#### Sites whose files live elsewhere

If your file storage is not on local disk — object storage through S3 File
System or Flysystem, say — add its scheme in `settings.php`:

```php
$settings['ai_integration_eca_allowed_schemes'] = ['s3'];
```

The list adds to the built-in three rather than replacing them, so `public://`
and the rest keep working. A scheme still has to be a stream wrapper that is
actually registered on the site, so a typo here fails closed rather than opening
anything up.

This lives in `settings.php`, not in configuration, because it decides what the
site is willing to open: keeping it there means it cannot be changed through the
UI or arrive with an imported configuration, the same reasoning core applies to
`trusted_host_patterns`.

## Chat system prompt

The Chat action has a **System prompt** field of its own, above the prompt. What
you enter there is sent as the system prompt of the request, and it supports the
same tokens as the prompt itself. Leave it empty to send no system prompt at all.

Before that field existed, a system prompt had to be written into the model
configuration below as `system_name` and `system_prompt`. Those keys are no
longer read: they are ordinary provider settings now, which most providers reject
as unknown parameters. Updating the module moves an existing value into the new
field and drops `system_name`, whose arbitrary role has no equivalent — providers
normalize the role of a system message themselves.

## Attaching files to a Chat

The Chat action has an optional **Files** field for sending images or documents
along with the prompt. It accepts any of:

- a single token holding a **list** of files — a file field on an entity such as
  `node:field_images`, or your own `my_files`;
- a single token holding **one** file — `my_file`, which may be a managed file
  entity, a scalar, or raw bytes;
- a **literal value** typed straight into the field, e.g. `public://logo.png`.
  Tokens embedded in a literal value are replaced first, so
  `public://[name].png` works too.

A token may be named bare (`node:field_images`), the way ECA's other
token-reference fields are filled in, or wrapped in brackets
(`[node:field_images]`) — both work. A value containing a dot or a slash is
always treated as a literal, so a path is never mistaken for a token name.

**Input source** next to the field says how each value is read — *Managed file
ID*, *File path or URI* or *Raw binary* — and applies to every file in the list,
so a list has to be uniform.

Each file is attached as an image when its MIME type is an image, and as a plain
file otherwise; what a provider then does with it is up to the provider (several
accept images and PDFs and ignore the rest). Raw binary has no filename to derive
a MIME type from, so it is detected from the file contents instead.

If a configured file cannot be read the action stops without calling the
provider, rather than sending a prompt that talks about a file it did not attach.

## Model configuration

Most actions also expose a YAML **configuration** field for model-specific
settings (temperature, voice, size, response format, …). The values are validated
against the selected provider's API definition when you save the action.
