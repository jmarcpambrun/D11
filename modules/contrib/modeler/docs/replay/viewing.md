# Viewing Replay Data

Replay lets you load past workflow execution data and step through it visually
on the canvas. This is invaluable for understanding how a workflow actually
behaved and for debugging issues.

## Loading replay data

1. **Select an event node** on the canvas (click on it).
2. Click the **reload button** in the event's Property Panel.
3. The modeler fetches the execution history from the backend.
4. The **Replay Panel** expands to show the loaded data.

![Loading replay data for an event node](../assets/screenshots/replay-load.jpg){ .screenshot }

## Multiple executions

If the event has been triggered multiple times, the Replay Panel shows an
**entry selector dropdown** at the top. Each entry includes:

- **Timestamp**: When the execution occurred.
- **User**: Who triggered it.
- **URL**: The page URL at the time of execution.

Click an entry to view its execution steps. The dropdown is only shown when
there are two or more entries.

## Navigating steps

The Replay Panel shows a list of execution steps. Each step represents one
action in the workflow execution:

| Step type | Icon | Meaning |
|-----------|------|---------|
| **Started** | Play icon | The workflow began executing at this event. |
| **Execute** | Arrow icon | An action was executed. |
| **Add successor** | Green check | A condition passed; execution continued. |
| **Ignore successor** | Red X | A condition failed; this path was skipped. |
| **Access denied** | Lock icon | Execution was blocked by permissions. |

### Manual navigation

- **Click a step**: Jump to that step. The corresponding node or edge is
  highlighted on the canvas.
- **Forward/Back buttons**: Move one step at a time.

### Auto-playback

- **Play**: Start automatic playback through all steps.
- **Speed**: Choose 0.5x, 1x, or 2x playback speed.
- **Pause**: Stop auto-play at the current step.
- **Stop**: Exit replay mode entirely and clear all highlights.

## Canvas visualization

During replay, the canvas shows visual indicators:

- **Node highlighting**: The active step's node is highlighted.
- **Edge indicators**: Floating circles at edge midpoints show condition results:
  - **Green circle with checkmark**: Condition passed.
  - **Red circle with X**: Condition failed.

The canvas automatically pans to keep the current step's element in view,
while preserving your zoom level.

## Bidirectional sync

The replay system syncs in both directions:

- **Click a step** in the Replay Panel --> the canvas highlights the
  corresponding element.
- **Click a node** on the canvas --> the Replay Panel jumps to the first
  matching step.

## Step metadata

Click the **i** button in a step's area to view detailed metadata:

- Step type
- Component ID
- Successor ID (for edge-related steps)
- Condition ID (for condition evaluations)
- Error information (if applicable)

## Resizing sections

The Replay Panel's internal sections (execution controls, step data, global
tokens, template tokens) are vertically resizable. Drag the horizontal
separator between sections to adjust their height. Your preferred proportions
are saved to local storage and restored on the next visit. See
[Replay Panel > Resizable sections](../interface/replay-panel.md#resizable-sections)
for details.

## Empty state

When no replay data is loaded, the panel shows guidance messages explaining
how to load data or run a test, depending on what capabilities are available.
