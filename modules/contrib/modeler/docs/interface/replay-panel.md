# Replay Panel

The **Replay Panel** sits between the canvas and the Property Panel. It lets you
visualize past workflow executions and run live tests directly from the modeler.

![Replay Panel showing execution steps with playback controls](../assets/screenshots/replay-panel.jpg){ .screenshot }

## Visibility

The Replay Panel is visible whenever the model has replay or test capabilities
configured. It auto-collapses when no replay data is loaded and auto-expands
when data becomes available.

For new models that have not been saved yet, the panel is hidden since there is
no execution history to display and testing requires a saved model.

## Loading replay data

1. Select an **event node** on the canvas.
2. Click the **reload** button in the event's Property Panel.
3. The Replay Panel expands and shows the execution history.

If multiple executions exist, an **entry selector** dropdown lets you switch
between them. Each entry shows a timestamp and metadata about the execution.

## Navigating execution steps

Once replay data is loaded:

- **Step list**: Each execution step is listed with an icon indicating its type
  (started, execute, condition passed, condition failed).
- **Click a step**: The corresponding node or edge is highlighted on the canvas,
  and the Property Panel shows its configuration.
- **Forward/Back buttons**: Navigate sequentially through steps.

### Playback controls

- **Play**: Automatically steps through the execution at the selected speed.
- **Pause**: Stops auto-play at the current step.
- **Speed**: Choose 0.5x, 1x, or 2x playback speed.
- **Stop**: Exits replay mode entirely.

## Visual indicators

During replay, the canvas shows:

- **Highlighted nodes**: The current step's node is highlighted.
- **Edge indicators**: Circular indicators appear at edge midpoints:
  - Green with a checkmark for conditions that **passed**.
  - Red with an X for conditions that **failed**.

## Step data

When a step is selected, the panel shows the **Step Data** section with token
values available at that point in the execution. Token data is displayed in a
collapsible tree structure.

!!! tip "Drag tokens into forms"
    You can drag individual tokens from the Step Data section directly into
    configuration form fields that support tokens. See
    [Tokens & Data](../replay/tokens.md) for details.

## Info popup

Click the **i** button in a step's header to view execution metadata:

- Step type
- Component ID
- Successor ID (for edge steps)
- Condition ID (for condition evaluations)
- Error information (if applicable)

## Global tokens

If the site provides global tokens (like `[site:name]` or
`[current-date:long]`), they appear in a **Global Tokens** section at the
bottom of the Replay Panel. These are always visible, regardless of whether
replay data is loaded.

Global tokens are also draggable into configuration form fields.

## Template tokens

When the model is marked as a **template**, a **Template Tokens** section
appears alongside the global tokens. These tokens are specific to the
template's context and are provided by the backend. They behave the same as
global tokens -- always visible and draggable into configuration form fields.

See [Tokens & Data](../replay/tokens.md) for details on using tokens.

## Resizable sections

The Replay Panel's internal sections (execution controls, step data, global
tokens, template tokens) are **vertically resizable**. Drag the horizontal
separator between any two sections to give more space to the section you need.

- **Minimum height**: Each section has a minimum height of 60 pixels.
- **Persistence**: Section proportions are saved to local storage and
  restored on the next visit.
- **Dynamic sections**: The number of sections adapts to context -- for
  example, template tokens only appear when the model is a template.

## Empty state messages

When no replay data is loaded, the panel shows context-specific guidance:

| Situation | Message |
|-----------|---------|
| Replay available | Select an event and use the reload button to load past execution data. |
| Replay + Test available | Shows both replay and test instructions. |
| Test available, event selected | Click Test to execute the workflow. |
| Test available, no event selected | Select an event and click Test. |
