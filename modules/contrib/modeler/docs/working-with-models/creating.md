# Creating a Model

## Starting a new model

To create a new model, navigate to the model owner's administration page and
click the **Add** button. For example, with ECA:

1. Go to **Administration > Configuration > Workflow > ECA**.
2. Click **Add ECA model**.
3. The modeler opens with an empty canvas.

## Empty canvas

A new model starts with no nodes or edges. The canvas shows a clean workspace
where you can begin building your workflow.

![Empty canvas ready for a new workflow](../assets/screenshots/modeler-empty.jpg){ .screenshot }

## Setting model metadata

Click the model title in the toolbar to open the **Metadata Modal**. Here you
can set:

| Field | Description |
|-------|-------------|
| **Label** | The human-readable name for your model. |
| **Description** | A text description of what the model does. |
| **Version** | Semantic version number (e.g., `1.0.0`). |
| **Tags** | Labels for organizing and filtering models. |
| **Documentation** | A documentation URL for the model. |
| **Executable** | Whether the model should be active and executable. |
| **Template** | Mark the model as a reusable template. |
| **Changelog** | Notes about changes in this version. |

!!! tip
    Set the model name and description early -- it helps you identify models
    in the administration list.

## Adding your first event

Every workflow needs at least one **event** (start node) to define what triggers
it. There are two ways to add an event:

Click the **+ Event** button in the toolbar. A popup appears with all available
event types. Select one to place it on the canvas.

## Building from there

Once you have an event on the canvas, you can build the rest of your workflow:

1. **Add actions**: Hover over the event and use the quick-add **+** button.
2. **Add conditions**: Hover over edges and use the quick-add **+** button.
   Alternatively, select a condition from a node's **+** button for
   condition-first authoring (creates a placeholder node with the condition
   pre-attached).
3. **Configure each component**: Click a node to open the Property Panel.

See [Editing a Model](editing.md) for the full editing guide.
