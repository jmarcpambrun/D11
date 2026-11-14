/**
 * @file
 * AI Integration ECA - Modeler plugin.
 *
 * Adds a toolbar toggle to the workflow modeler that opens a chat panel. The
 * panel sends the model as it currently sits on the canvas, together with the
 * user's question, to the Drupal backend, which asks the configured AI
 * provider about it.
 *
 * An answer may come back with a list of components the assistant recommends
 * adding, and with a plan of changes it proposes making. Both are rendered
 * below the answer and both wait for the user: a component goes on the canvas
 * when its button is pressed, and a plan is applied only as a whole, only
 * once, and only when the user asks for it.
 *
 * The declarations below are ordered so that every function is defined before
 * it is used, which is what the "no-use-before-define" rule asks for. That
 * puts the low level helpers first and the Drupal behavior last.
 */

(function (Drupal, once) {
  Drupal.aiIntegrationEcaModelerPlugin =
    Drupal.aiIntegrationEcaModelerPlugin || {};

  const PANEL_ID = 'ai_integration_eca_panel';
  const WIDGET_ID = 'ai_integration_eca_toggle';
  const ENDPOINT = 'ai-integration-eca/modeler-assistant/chat';

  /**
   * Number of messages kept and sent back as conversation history.
   */
  const MAX_HISTORY = 20;

  /**
   * Maps the modeler_api component type constants to their label keys.
   */
  const COMPONENT_TYPE_KEYS = {
    1: 'start',
    2: 'subprocess',
    3: 'swimlane',
    4: 'element',
    5: 'link',
    6: 'gateway',
    7: 'annotation',
  };

  /**
   * The component types that addNode() can put on the canvas.
   *
   * A condition belongs to a connection rather than to the canvas, and a
   * swimlane or an annotation is not a step at all, so a suggestion carrying
   * one of those is shown but cannot be added from here.
   */
  const ADDABLE_COMPONENT_TYPES = [1, 2, 4, 6];

  let panelActive = false;

  /**
   * The session CSRF token, fetched once and reused.
   */
  let csrfToken = null;

  /**
   * The conversation, kept outside the panel so that closing and reopening
   * the panel does not throw the discussion away.
   */
  let messages = [];

  /**
   * The conversation ID, assigned by the backend on the first answer.
   */
  let threadId = null;

  /**
   * Whether a request is currently in flight.
   */
  let busy = false;

  /**
   * The unsubscribe callbacks of the subscriptions the open panel holds.
   *
   * Plugin registrations survive a remount of the modeler by design, so the
   * panel is rendered again every time it comes back. Without releasing the
   * subscriptions of the previous rendering, each remount would leave one
   * more callback behind writing into a container nobody can see any more.
   */
  let panelSubscriptions = [];

  /**
   * Releases the subscriptions the open panel holds.
   */
  function releasePanelSubscriptions() {
    panelSubscriptions.forEach(function (unsubscribe) {
      unsubscribe();
    });
    panelSubscriptions = [];
  }

  /**
   * Names a component type the way the model owner names it.
   *
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {number} componentType
   *   The modeler_api component type constant.
   *
   * @return {string}
   *   The name, or an empty string when the type is not known.
   */
  function componentTypeName(api, componentType) {
    const key = COMPONENT_TYPE_KEYS[componentType];
    if (!key) {
      return '';
    }

    const labels =
      (typeof api.getComponentLabels === 'function' &&
        api.getComponentLabels()) ||
      {};
    if (typeof labels[key] === 'string' && labels[key] !== '') {
      return labels[key];
    }

    // The model owner normally supplies the wording. These stand in when it
    // does not, and follow the vocabulary ECA uses.
    const fallbacks = {
      start: Drupal.t('Event'),
      subprocess: Drupal.t('Subprocess'),
      swimlane: Drupal.t('Swimlane'),
      element: Drupal.t('Action'),
      link: Drupal.t('Condition'),
      gateway: Drupal.t('Gateway'),
      annotation: Drupal.t('Annotation'),
    };

    return fallbacks[key];
  }

  /**
   * Puts a suggested component on the canvas and moves to it.
   *
   * @param {object} suggestion
   *   The suggestion, as validated by the backend.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {string|null}
   *   The ID of the new component, or null when the modeler refused it.
   */
  function addSuggestion(suggestion, api) {
    const descriptor = {
      plugin: suggestion.plugin,
      componentType: suggestion.componentType,
    };
    // Left out rather than sent empty, because the modeler then derives a
    // label from the plugin ID instead of showing a nameless component.
    if (suggestion.label) {
      descriptor.label = suggestion.label;
    }

    const nodeId = api.addNode(descriptor);
    if (!nodeId) {
      return null;
    }

    api.selectNode(nodeId);
    api.focusNode(nodeId);

    return nodeId;
  }

  /**
   * Builds the line that replaces the button once a component was added.
   *
   * @return {HTMLElement}
   *   The confirmation element.
   */
  function buildAddedNotice() {
    const notice = document.createElement('p');
    notice.className = 'ai-suggestion-added';
    notice.textContent = Drupal.t('Added to the canvas.');

    return notice;
  }

  /**
   * Builds the card describing one suggested component.
   *
   * Every value in a suggestion was written by an AI provider, so all of them
   * are inserted as text and never as markup.
   *
   * @param {object} suggestion
   *   The suggestion, as validated by the backend.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {boolean} readOnly
   *   Whether the modeler is in read-only mode.
   * @param {Function} onError
   *   Called with a message when the component cannot be added, and with an
   *   empty string when it was.
   *
   * @return {HTMLElement}
   *   The card.
   */
  function buildSuggestionCard(suggestion, api, readOnly, onError) {
    const item = document.createElement('li');
    item.className = 'ai-suggestion';

    const label = document.createElement('p');
    label.className = 'ai-suggestion-label';
    label.textContent = suggestion.label || suggestion.plugin;
    item.appendChild(label);

    const typeName = componentTypeName(api, suggestion.componentType);
    const meta = document.createElement('p');
    meta.className = 'ai-suggestion-meta';
    meta.textContent = typeName
      ? Drupal.t('@plugin (@type)', {
          '@plugin': suggestion.plugin,
          '@type': typeName,
        })
      : suggestion.plugin;
    item.appendChild(meta);

    if (suggestion.reason) {
      const reason = document.createElement('p');
      reason.className = 'ai-suggestion-reason';
      reason.textContent = suggestion.reason;
      item.appendChild(reason);
    }

    if (suggestion.added) {
      item.appendChild(buildAddedNotice());
      return item;
    }

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'ai-add-btn';
    addBtn.textContent = Drupal.t('Add to canvas');

    if (readOnly) {
      addBtn.disabled = true;
      addBtn.title = Drupal.t('This model is read-only.');
    } else if (!ADDABLE_COMPONENT_TYPES.includes(suggestion.componentType)) {
      addBtn.disabled = true;
      addBtn.title = Drupal.t(
        'This kind of component cannot be placed on the canvas. A condition, for example, belongs to a connection.',
      );
    } else {
      addBtn.addEventListener('click', function () {
        if (!addSuggestion(suggestion, api)) {
          onError(Drupal.t('The component could not be added to the canvas.'));
          return;
        }
        // The flag is kept on the suggestion, which lives in the message, so
        // that a redraw of the log does not offer the same component again.
        suggestion.added = true;
        onError('');
        addBtn.replaceWith(buildAddedNotice());
      });
    }

    item.appendChild(addBtn);

    return item;
  }

  /**
   * Builds the list of components suggested by one answer.
   *
   * @param {object} message
   *   The assistant message the suggestions belong to.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {Function} onError
   *   Called with a message when a component cannot be added.
   *
   * @return {HTMLElement}
   *   The list.
   */
  function renderSuggestionList(message, api, onError) {
    const readOnly =
      typeof api.isReadOnly === 'function' ? api.isReadOnly() : false;

    const list = document.createElement('ul');
    list.className = 'ai-suggestions';
    list.setAttribute('aria-label', Drupal.t('Suggested components'));

    message.suggestions.forEach(function (suggestion) {
      list.appendChild(buildSuggestionCard(suggestion, api, readOnly, onError));
    });

    return list;
  }

  /**
   * Creates a lookup that no plugin identifier can accidentally reach into.
   *
   * A reference token is a short word the assistant invents, so nothing stops
   * it from choosing "constructor" or "toString". Looking such a token up in
   * an ordinary object would find a member of Object.prototype and resolve it
   * to something that is not an ID at all.
   *
   * @return {object}
   *   An empty object with no prototype.
   */
  function createLookup() {
    return Object.create(null);
  }

  /**
   * Resolves a reference to the identifier it stands for.
   *
   * @param {string} reference
   *   An identifier from the canvas, or a token an earlier operation minted.
   * @param {object} lookup
   *   The tokens minted so far.
   *
   * @return {string}
   *   The identifier to hand to the modeler.
   */
  function resolveReference(reference, lookup) {
    return lookup[reference] || reference;
  }

  /**
   * Names a component for the plan description.
   *
   * @param {string} reference
   *   The reference used in the plan.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {object} labels
   *   The labels of the components the plan creates.
   *
   * @return {string}
   *   The label, falling back to the reference itself.
   */
  function nodeReferenceLabel(reference, api, labels) {
    if (labels[reference]) {
      return labels[reference];
    }

    const node =
      typeof api.getNodeById === 'function' ? api.getNodeById(reference) : null;

    return (node && node.data && node.data.label) || reference;
  }

  /**
   * Names a connection for the plan description.
   *
   * @param {string} reference
   *   The reference used in the plan.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {object} refs
   *   The labels of the components and connections the plan creates.
   *
   * @return {string}
   *   A readable description of the connection.
   */
  function edgeReferenceLabel(reference, api, refs) {
    if (refs.edges[reference]) {
      return refs.edges[reference];
    }

    const edge =
      typeof api.getEdgeById === 'function' ? api.getEdgeById(reference) : null;
    if (!edge) {
      return reference;
    }

    return Drupal.t('@source to @target', {
      '@source': nodeReferenceLabel(edge.source, api, refs.nodes),
      '@target': nodeReferenceLabel(edge.target, api, refs.nodes),
    });
  }

  /**
   * Describes what an updateNode operation changes.
   *
   * @param {object} operation
   *   The operation.
   *
   * @return {Array}
   *   The names of the properties it touches.
   */
  function updatedProperties(operation) {
    const changed = [];
    if (operation.label !== null && operation.label !== undefined) {
      changed.push(Drupal.t('label'));
    }
    if (operation.annotation !== null && operation.annotation !== undefined) {
      changed.push(Drupal.t('annotation'));
    }
    if (
      operation.configuration !== null &&
      operation.configuration !== undefined
    ) {
      changed.push(Drupal.t('configuration'));
    }

    return changed;
  }

  /**
   * Turns a plan into one readable sentence per operation.
   *
   * The user is being asked to approve this, so it has to read as a list of
   * changes to a model rather than as the data the assistant sent.
   *
   * @param {Array} operations
   *   The validated plan.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {Array}
   *   One sentence per operation, in the same order.
   */
  function describePlan(operations, api) {
    const refs = { nodes: createLookup(), edges: createLookup() };

    return operations.map(function (operation) {
      if (operation.kind === 'addNode') {
        const label = operation.label || operation.plugin;
        if (operation.ref) {
          refs.nodes[operation.ref] = label;
        }
        const typeName = componentTypeName(api, operation.componentType);

        return typeName
          ? Drupal.t('Add the @type "@label".', {
              '@type': typeName,
              '@label': label,
            })
          : Drupal.t('Add "@label".', { '@label': label });
      }

      if (operation.kind === 'addEdge') {
        const source = nodeReferenceLabel(operation.source, api, refs.nodes);
        const target = nodeReferenceLabel(operation.target, api, refs.nodes);
        if (operation.ref) {
          refs.edges[operation.ref] = Drupal.t('@source to @target', {
            '@source': source,
            '@target': target,
          });
        }

        return Drupal.t('Connect "@source" to "@target".', {
          '@source': source,
          '@target': target,
        });
      }

      if (operation.kind === 'setCondition') {
        return Drupal.t('Put the condition "@label" on the connection @edge.', {
          '@label': operation.label || operation.plugin,
          '@edge': edgeReferenceLabel(operation.edge, api, refs),
        });
      }

      if (operation.kind === 'updateNode') {
        return Drupal.t('Change the @properties of "@node".', {
          '@properties': updatedProperties(operation).join(', '),
          '@node': nodeReferenceLabel(operation.node, api, refs.nodes),
        });
      }

      return operation.kind;
    });
  }

  /**
   * Works out which component each new one is being added underneath.
   *
   * The modeler places a new component the way its own quick-add would when
   * it is told which component it follows, and as a new flow of its own when
   * it is not. A plan already says which is which: if something connects to a
   * component the plan creates, that connection's source is its parent.
   *
   * Only a parent that exists by the time the component is added counts. A
   * plan may connect a new component to one created further down the list,
   * and naming that as the parent would have the modeler reject the whole
   * operation, so those are left to be placed as their own flow instead.
   *
   * @param {Array} operations
   *   The validated plan.
   *
   * @return {Array}
   *   One entry per operation: the reference of the parent for an addNode
   *   that has a usable one, otherwise an empty string.
   */
  function derivePlanParents(operations) {
    // Which operation mints each token, so a parent can be told apart from
    // one that does not exist yet.
    const mintedAt = createLookup();
    operations.forEach(function (operation, index) {
      if (operation.kind === 'addNode' && operation.ref) {
        mintedAt[operation.ref] = index;
      }
    });

    return operations.map(function (operation, index) {
      if (operation.kind !== 'addNode' || !operation.ref) {
        return '';
      }

      const connection = operations.find(function (candidate, candidateIndex) {
        return (
          candidateIndex > index &&
          candidate.kind === 'addEdge' &&
          candidate.target === operation.ref
        );
      });
      if (!connection) {
        return '';
      }

      const parent = connection.source;
      const parentMintedAt = mintedAt[parent];

      // Undefined means the parent is already on the canvas. A number after
      // this operation means it has not been created yet.
      return parentMintedAt === undefined || parentMintedAt < index
        ? parent
        : '';
    });
  }

  /**
   * Carries out one operation of a plan.
   *
   * @param {object} operation
   *   The operation.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {object} refs
   *   The identifiers minted by earlier operations, extended by this one.
   * @param {string} parentReference
   *   The component the new one is being added underneath, or an empty string
   *   to have it placed as a flow of its own.
   *
   * @return {boolean}
   *   Whether the modeler carried it out.
   */
  function runOperation(operation, api, refs, parentReference) {
    if (operation.kind === 'addNode') {
      const descriptor = {
        plugin: operation.plugin,
        componentType: operation.componentType,
      };
      // Placement only: this creates no connection, so the plan's own
      // addEdge operations still do that work.
      if (parentReference) {
        descriptor.sourceNodeId = resolveReference(parentReference, refs.nodes);
      }
      if (operation.label) {
        descriptor.label = operation.label;
      }
      if (
        operation.configuration &&
        Object.keys(operation.configuration).length
      ) {
        descriptor.configuration = operation.configuration;
      }

      const nodeId = api.addNode(descriptor);
      if (!nodeId) {
        return false;
      }
      if (operation.ref) {
        refs.nodes[operation.ref] = nodeId;
      }
      refs.created.push(nodeId);

      return true;
    }

    if (operation.kind === 'addEdge') {
      const edgeId = api.addEdge(
        resolveReference(operation.source, refs.nodes),
        resolveReference(operation.target, refs.nodes),
      );
      if (!edgeId) {
        return false;
      }
      if (operation.ref) {
        refs.edges[operation.ref] = edgeId;
      }

      return true;
    }

    if (operation.kind === 'setCondition') {
      const descriptor = { plugin: operation.plugin };
      if (operation.label) {
        descriptor.label = operation.label;
      }
      if (
        operation.configuration &&
        Object.keys(operation.configuration).length
      ) {
        descriptor.configuration = operation.configuration;
      }

      return (
        api.setCondition(
          resolveReference(operation.edge, refs.edges),
          descriptor,
        ) === true
      );
    }

    if (operation.kind === 'updateNode') {
      const updates = {};
      if (operation.label !== null && operation.label !== undefined) {
        updates.label = operation.label;
      }
      if (operation.annotation !== null && operation.annotation !== undefined) {
        updates.annotation = operation.annotation;
      }
      if (
        operation.configuration !== null &&
        operation.configuration !== undefined
      ) {
        updates.configuration = operation.configuration;
      }

      return (
        api.updateNode(
          resolveReference(operation.node, refs.nodes),
          updates,
        ) === true
      );
    }

    return false;
  }

  /**
   * Applies a plan to the canvas, stopping at the first refusal.
   *
   * Stopping rather than carrying on means the user is left with a prefix of
   * the plan they approved, which they can undo, rather than with an
   * arbitrary subset of it.
   *
   * @param {object} message
   *   The assistant message the plan belongs to.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {object}
   *   An object with the number of operations "applied", the index of the
   *   operation that stopped the plan in "failedAt" (null when all of them
   *   ran), the number of components the canvas did not keep in "lost", and
   *   the number of "steps" the user has to undo.
   */
  function applyPlan(message, api) {
    const refs = { nodes: createLookup(), edges: createLookup(), created: [] };
    const parents = derivePlanParents(message.operations);
    let applied = 0;
    let failedAt = null;

    message.operations.every(function (operation, index) {
      if (runOperation(operation, api, refs, parents[index])) {
        applied += 1;
        return true;
      }
      failedAt = index;
      return false;
    });

    // Every operation reported success, which is not the same as the canvas
    // having kept what they made. Ask the graph itself rather than trusting
    // the return values, so that a plan which quietly evaporated is reported
    // as the failure it is instead of as the success it claimed to be.
    const lost = refs.created.filter(function (nodeId) {
      return (
        typeof api.getNodeById !== 'function' ||
        api.getNodeById(nodeId) === null
      );
    }).length;

    // Each new component was placed as it was added, by telling the modeler
    // which component it follows, so there is nothing to lay out afterwards.
    // Laying the whole model out instead would move components the user
    // positioned deliberately, and cost an undo step of its own.
    if (applied > 0) {
      api.fitView();
    }

    return { applied, failedAt, lost, steps: applied };
  }

  /**
   * Reports what applying a plan did to the canvas.
   *
   * The undo count is stated because the modeler records one history entry
   * per mutation, so a plan is not undone by a single undo. Saying how many
   * steps it takes is more use than implying it is one.
   *
   * @param {object} message
   *   The assistant message the plan belongs to.
   * @param {Array} descriptions
   *   The plan as sentences, used to name the step that stopped it.
   *
   * @return {HTMLElement}
   *   The summary element.
   */
  function buildPlanSummary(message, descriptions) {
    const result = message.planResult || {
      applied: 0,
      failedAt: null,
      lost: 0,
      steps: 0,
    };
    const summary = document.createElement('p');
    summary.className = 'ai-plan-note';

    if (result.failedAt !== null) {
      summary.classList.add('ai-plan-note--warning');
      summary.textContent = Drupal.t(
        'Applied @applied of @total steps, then stopped at "@step". The rest of the plan was not applied.',
        {
          '@applied': result.applied,
          '@total': message.operations.length,
          '@step': descriptions[result.failedAt] || '',
        },
      );

      return summary;
    }

    if (result.lost > 0) {
      summary.classList.add('ai-plan-note--warning');
      summary.textContent = Drupal.t(
        'The plan ran, but @count of the components it added are no longer on the canvas. Undo the plan and report this.',
        { '@count': result.lost },
      );

      return summary;
    }

    summary.textContent = Drupal.t(
      'The plan was applied. Undo reverses it one step at a time, @count steps in all.',
      { '@count': result.steps },
    );

    return summary;
  }

  /**
   * Renders the plan an answer proposed, for the user to review and apply.
   *
   * Nothing here touches the canvas on its own. The plan is a proposal until
   * the user presses the button, which is the whole point of showing it as a
   * list of sentences rather than applying it and reporting afterwards.
   *
   * @param {object} message
   *   The assistant message the plan belongs to.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   * @param {Function} onError
   *   Called with a message when the plan cannot be applied.
   * @param {Function} redraw
   *   Redraws the conversation once the plan has been dealt with.
   *
   * @return {HTMLElement}
   *   The plan element.
   */
  function renderPlan(message, api, onError, redraw) {
    const descriptions = describePlan(message.operations, api);

    const section = document.createElement('div');
    section.className = 'ai-plan';

    const heading = document.createElement('p');
    heading.className = 'ai-plan-heading';
    heading.textContent = Drupal.t('Proposed plan');
    section.appendChild(heading);

    const list = document.createElement('ol');
    list.className = 'ai-plan-steps';
    descriptions.forEach(function (description) {
      const item = document.createElement('li');
      item.textContent = description;
      list.appendChild(item);
    });
    section.appendChild(list);

    if (message.planState === 'applied') {
      section.appendChild(buildPlanSummary(message, descriptions));

      return section;
    }

    if (message.planState === 'dismissed') {
      const dismissed = document.createElement('p');
      dismissed.className = 'ai-plan-note';
      dismissed.textContent = Drupal.t('Plan dismissed.');
      section.appendChild(dismissed);

      return section;
    }

    const actions = document.createElement('div');
    actions.className = 'ai-plan-actions';

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'ai-apply-btn';
    applyBtn.textContent = Drupal.t('Apply plan');

    if (typeof api.isReadOnly === 'function' && api.isReadOnly()) {
      applyBtn.disabled = true;
      applyBtn.title = Drupal.t('This model is read-only.');
    } else {
      applyBtn.addEventListener('click', function () {
        // A plan is applied once. Without this the button could be pressed
        // again between the mutations and the redraw.
        if (message.planState !== 'pending') {
          return;
        }
        message.planState = 'applied';
        message.planResult = applyPlan(message, api);
        onError(
          message.planResult.failedAt === null && message.planResult.lost === 0
            ? ''
            : Drupal.t('The plan could not be applied in full.'),
        );
        redraw();
      });
    }
    actions.appendChild(applyBtn);

    const dismissBtn = document.createElement('button');
    dismissBtn.type = 'button';
    dismissBtn.className = 'ai-dismiss-btn';
    dismissBtn.textContent = Drupal.t('Dismiss');
    dismissBtn.addEventListener('click', function () {
      message.planState = 'dismissed';
      redraw();
    });
    actions.appendChild(dismissBtn);

    section.appendChild(actions);

    return section;
  }

  /**
   * Returns every component and connection selected in the modeler.
   *
   * The plural getters arrived after modeler 1.0.x. Feature detection keeps
   * the assistant working with released versions, whose singular getters are
   * wrapped to provide the same array-shaped result.
   *
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {object}
   *   The selected nodes and edges.
   */
  function getSelection(api) {
    let nodes;
    if (typeof api.getSelectedNodes === 'function') {
      nodes = api.getSelectedNodes();
    } else {
      const node =
        typeof api.getSelectedNode === 'function'
          ? api.getSelectedNode()
          : null;
      nodes = node ? [node] : [];
    }

    let edges;
    if (typeof api.getSelectedEdges === 'function') {
      edges = api.getSelectedEdges();
    } else {
      const edge =
        typeof api.getSelectedEdge === 'function'
          ? api.getSelectedEdge()
          : null;
      edges = edge ? [edge] : [];
    }

    return { nodes, edges };
  }

  /**
   * Collects the model as it currently sits on the canvas.
   *
   * Only the properties that describe the model are collected. Canvas
   * coordinates and the modeler's own rendering state are left behind, both
   * to keep the request small and because the backend has no use for them.
   *
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {object}
   *   The snapshot.
   */
  function buildSnapshot(api) {
    const selection = getSelection(api);

    return {
      model: typeof api.getModelData === 'function' ? api.getModelData() : null,
      componentLabels:
        typeof api.getComponentLabels === 'function'
          ? api.getComponentLabels()
          : null,
      // Without the catalog the assistant has no way of knowing which
      // components this site actually offers, and answers with plugin IDs
      // that look right but do not exist. The backend decides which of them
      // are worth describing, so only the fields it reads are sent.
      components:
        typeof api.getComponents === 'function'
          ? api.getComponents().map(function (component) {
              return {
                plugin: component.plugin,
                label: component.label,
                type: component.type,
                provider: component.provider,
                description: component.description,
                componentType: component.componentType,
              };
            })
          : null,
      nodes: api.getNodes().map(function (node) {
        const data = node.data || {};
        return {
          id: node.id,
          data: {
            label: data.label,
            plugin: data.plugin,
            componentType: data.componentType,
            description: data.description,
            annotation: data.annotation,
            configuration: data.configuration,
          },
        };
      }),
      edges: api.getEdges().map(function (edge) {
        const data = edge.data || {};
        return {
          id: edge.id,
          source: edge.source,
          target: edge.target,
          data: {
            condition: data.condition,
            conditionLabel: data.conditionLabel,
            conditionConfiguration: data.conditionConfiguration,
            annotation: data.annotation,
          },
        };
      }),
      selection: {
        nodeIds: selection.nodes.map(function (node) {
          return node.id;
        }),
        edgeIds: selection.edges.map(function (edge) {
          return edge.id;
        }),
      },
    };
  }

  /**
   * Fetches the session CSRF token, reusing it once it is known.
   *
   * @return {Promise}
   *   Resolves with the token.
   */
  function getCsrfToken() {
    if (csrfToken) {
      return Promise.resolve(csrfToken);
    }

    return fetch(Drupal.url('session/token'), { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error(
            Drupal.t(
              'The session could not be verified. Please reload the page.',
            ),
          );
        }
        return response.text();
      })
      .then(function (token) {
        csrfToken = token;
        return token;
      });
  }

  /**
   * Sends a question and the current model to the backend.
   *
   * @param {string} prompt
   *   The question.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   *
   * @return {Promise}
   *   Resolves with the decoded response body.
   */
  function ask(prompt, api) {
    return getCsrfToken()
      .then(function (token) {
        return fetch(Drupal.url(ENDPOINT), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token,
          },
          body: JSON.stringify({
            prompt,
            threadId,
            // The newest message is the question itself, which is sent
            // separately, so it is not repeated here. Only the conversation
            // travels: the suggestions attached to a message are panel state
            // and mean nothing to the backend.
            history: messages
              .slice(0, -1)
              .slice(-MAX_HISTORY)
              .map(function (message) {
                return { role: message.role, text: message.text };
              }),
            snapshot: buildSnapshot(api),
          }),
        });
      })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            if (!response.ok || !data.reply) {
              throw new Error(
                data.error ||
                  Drupal.t('The AI assistant could not answer this question.'),
              );
            }
            return data;
          });
      });
  }

  /**
   * Renders the AI assistant panel contents.
   *
   * @param {HTMLElement} container
   *   The panel container element.
   * @param {object} api
   *   The WorkflowModeler plugin API.
   */
  function renderAssistantPanel(container, api) {
    container.innerHTML = '';
    container.classList.add('ai-assistant-panel');

    // This introduction is permanent because the same guidance used to be
    // the chat log's empty state and vanished with the first message.
    const intro = document.createElement('p');
    intro.className = 'ai-panel-intro';
    intro.textContent = Drupal.t(
      'Ask a question about this model. To focus the answer, select one or more components on the canvas first. The whole model is always available to the assistant.',
    );
    container.appendChild(intro);

    // The modeler puts the registered label in its own panel header, so a
    // heading here would show "AI Assistant" twice. This row carries the
    // current model status and the button that starts a new conversation. It
    // sits inside the panel's content element, which is a sibling of that
    // header rather than part of it, so the button is never inside the area a
    // floating panel is dragged by.
    const header = document.createElement('div');
    header.className = 'ai-panel-header';

    const status = document.createElement('p');
    status.className = 'ai-status';
    header.appendChild(status);

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'ai-clear-btn';
    clearBtn.textContent = Drupal.t('Clear');
    clearBtn.title = Drupal.t('Start a new conversation');
    header.appendChild(clearBtn);

    container.appendChild(header);

    const log = document.createElement('div');
    log.className = 'ai-chat-log';
    log.setAttribute('role', 'log');
    log.setAttribute('aria-live', 'polite');
    log.setAttribute('aria-label', Drupal.t('Conversation'));
    container.appendChild(log);

    const error = document.createElement('div');
    error.className = 'ai-chat-error';
    error.setAttribute('role', 'alert');
    error.hidden = true;
    container.appendChild(error);

    const form = document.createElement('form');
    form.className = 'ai-chat-form';

    const inputId = `ai-chat-input-${Date.now()}`;
    const label = document.createElement('label');
    label.className = 'visually-hidden';
    label.setAttribute('for', inputId);
    label.textContent = Drupal.t('Your question');
    form.appendChild(label);

    const input = document.createElement('textarea');
    input.id = inputId;
    input.className = 'ai-chat-input';
    input.rows = 3;
    input.placeholder = Drupal.t('Type your question here');
    form.appendChild(input);

    const sendBtn = document.createElement('button');
    sendBtn.type = 'submit';
    sendBtn.className = 'ai-send-btn';
    sendBtn.textContent = Drupal.t('Send');
    form.appendChild(sendBtn);

    container.appendChild(form);

    /**
     * Reflects the busy state in the panel.
     *
     * @param {boolean} isBusy
     *   Whether a request is in flight.
     */
    function setBusy(isBusy) {
      busy = isBusy;
      sendBtn.disabled = isBusy;
      input.disabled = isBusy;
      log.setAttribute('aria-busy', isBusy ? 'true' : 'false');
      sendBtn.textContent = isBusy ? Drupal.t('Thinking...') : Drupal.t('Send');
    }

    /**
     * Shows an error message, or hides the error area when given nothing.
     *
     * @param {string} message
     *   The message to show.
     */
    function showError(message) {
      error.textContent = message || '';
      error.hidden = !message;
    }

    /**
     * Redraws the conversation.
     */
    function renderLog() {
      log.innerHTML = '';

      messages.forEach(function (message) {
        const entry = document.createElement('div');
        entry.className = `ai-chat-message ai-chat-message--${message.role}`;

        const who = document.createElement('span');
        who.className = 'ai-chat-role';
        who.textContent =
          message.role === 'user' ? Drupal.t('You') : Drupal.t('Assistant');
        entry.appendChild(who);

        // The answer comes from an AI provider, so it is rendered as text.
        // The CSS keeps the line breaks readable.
        const text = document.createElement('p');
        text.className = 'ai-chat-text';
        text.textContent = message.text;
        entry.appendChild(text);

        if (Array.isArray(message.suggestions) && message.suggestions.length) {
          entry.appendChild(renderSuggestionList(message, api, showError));
        }

        if (Array.isArray(message.operations) && message.operations.length) {
          entry.appendChild(renderPlan(message, api, showError, renderLog));
        }

        log.appendChild(entry);
      });

      log.scrollTop = log.scrollHeight;
    }

    /**
     * Updates the line describing what the question will be asked about.
     */
    function renderStatus() {
      const count = api.getNodes().length;
      const countText = Drupal.formatPlural(
        count,
        '1 component',
        '@count components',
      );
      const selection = getSelection(api);
      const nodes = selection.nodes;
      const edges = selection.edges;
      let focusText;

      if (!nodes.length && !edges.length) {
        focusText = Drupal.t('asking about the whole model');
      } else if (nodes.length === 1 && !edges.length) {
        const node = nodes[0];
        focusText = Drupal.t('focused on "@label"', {
          '@label': (node.data && node.data.label) || node.id,
        });
      } else if (edges.length === 1 && !nodes.length) {
        focusText = Drupal.t('focused on the selected connection');
      } else {
        // This branch always describes at least two items, so plural handling
        // is unnecessary.
        focusText = Drupal.t('focused on @count selected items', {
          '@count': nodes.length + edges.length,
        });
      }

      status.textContent = `${countText} \u00b7 ${focusText}`;
    }

    /**
     * Sends the current question to the backend.
     */
    function submit() {
      if (busy) {
        return;
      }
      const prompt = input.value.trim();
      if (!prompt) {
        return;
      }

      showError('');
      messages.push({ role: 'user', text: prompt });
      input.value = '';
      renderLog();
      setBusy(true);

      ask(prompt, api)
        .then(function (data) {
          threadId = data.threadId || threadId;
          messages.push({
            role: 'assistant',
            text: data.reply,
            // Kept on the message rather than beside it, so that redrawing
            // the log keeps every answer with the components it proposed.
            suggestions: Array.isArray(data.suggestions)
              ? data.suggestions
              : [],
            operations: Array.isArray(data.operations) ? data.operations : [],
            // A plan is a proposal until the user says otherwise.
            planState: 'pending',
            planResult: null,
          });
          if (messages.length > MAX_HISTORY) {
            messages = messages.slice(-MAX_HISTORY);
          }
          renderLog();
        })
        .catch(function (err) {
          showError(
            err.message || Drupal.t('The AI assistant could not be reached.'),
          );
        })
        .then(function () {
          setBusy(false);
          renderLog();
          input.focus();
        });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submit();
    });

    input.addEventListener('keydown', function (event) {
      // Enter sends, Shift+Enter adds a line break.
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
      }
    });

    clearBtn.addEventListener('click', function () {
      messages = [];
      threadId = null;
      showError('');
      renderLog();
      input.focus();
    });

    renderStatus();
    renderLog();
    setBusy(busy);

    // The subscriptions are held on to so that the panel's destroy callback
    // can release them again. A remount would otherwise leave the previous
    // rendering subscribed for the lifetime of the page. Selection changes
    // include a multi-selection growing or shrinking.
    releasePanelSubscriptions();
    panelSubscriptions = [
      api.onSelectionChange(renderStatus),
      api.onNodesChange(renderStatus),
    ].filter(function (unsubscribe) {
      return typeof unsubscribe === 'function';
    });
  }

  /**
   * Registers the toolbar widget with the WorkflowModeler.
   */
  Drupal.aiIntegrationEcaModelerPlugin.registerToggleWidget = function () {
    const modeler = window.WorkflowModeler;
    if (!modeler) {
      return;
    }

    try {
      modeler.registerWidget({
        id: WIDGET_ID,
        label: Drupal.t('AI Assistant'),
        position: 'right',
        weight: 5,
        render(container) {
          const btn = document.createElement('button');
          btn.className = 'toolbar-btn';
          btn.type = 'button';
          btn.title = Drupal.t('Toggle AI Assistant');
          btn.setAttribute('aria-label', Drupal.t('Toggle AI Assistant'));
          btn.setAttribute('aria-pressed', 'false');

          const icon = document.createElement('span');
          icon.className = 'ai-icon';
          icon.textContent = 'AI';
          btn.appendChild(icon);

          btn.addEventListener('click', function () {
            if (panelActive) {
              modeler.unregisterPanel(PANEL_ID);
              btn.classList.remove('active');
              btn.setAttribute('aria-pressed', 'false');
              panelActive = false;
            } else {
              modeler.registerPanel({
                id: PANEL_ID,
                label: Drupal.t('AI Assistant'),
                position: 'left',
                width: 380,
                weight: 0,
                // Float above the canvas instead of taking a slot beside it,
                // so asking a question does not squeeze the model the
                // question is about. The user moves the panel by its header.
                // With "floating" the position above only seeds the corner it
                // first appears at.
                //
                // The option arrived after modeler 1.0.x. On an older
                // modeler registerPanel() validates only "id" and "render"
                // and spreads the rest of the descriptor, so this key is
                // simply carried through and ignored, and the panel docks as
                // it did before. No feature detection needed.
                floating: true,
                render(panelContainer, panelApi) {
                  renderAssistantPanel(panelContainer, panelApi);
                },
                destroy(panelContainer) {
                  releasePanelSubscriptions();
                  panelContainer.innerHTML = '';
                },
              });
              btn.classList.add('active');
              btn.setAttribute('aria-pressed', 'true');
              panelActive = true;
            }
          });

          container.appendChild(btn);
        },
        destroy(container) {
          if (panelActive) {
            modeler.unregisterPanel(PANEL_ID);
            panelActive = false;
          }
          container.innerHTML = '';
        },
      });
    } catch (err) {
      Drupal.throwError(err);
    }
  };

  Drupal.behaviors.aiIntegrationEcaModelerPlugin = {
    attach(context) {
      once('ai-integration-eca-plugin', 'html', context).forEach(function () {
        document.addEventListener(
          'workflow-modeler:ready',
          Drupal.aiIntegrationEcaModelerPlugin.registerToggleWidget,
          { once: true },
        );
      });
    },
  };
})(Drupal, once);
