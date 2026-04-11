import { v4 as uuidv4 } from 'uuid';
import type { StoreNode as Node, StoreEdge as Edge, ModelData } from '../types/settings';
import { EDGE_STYLING, LAYOUT, NODE_DIMENSIONS, VIEWPORT } from '../constants/dimensions';
import { resolveNodeType } from './componentUtils';
import {
  findStartNodes,
  buildGraphData,
} from './layoutHelpers';
import {
  processFlowLayout,
  convertPositionsToCoordinates,
  groupNodesByRow,
  optimizeRowAlignment,
} from './layoutStrategies';

// Type definitions for React Flow
interface Viewport {
  x: number;
  y: number;
  zoom: number;
}

interface BoundingBox {
  x: number;
  y: number;
  width: number;
  height: number;
}

/**
 * Map node type back to component type constant
 */
function getComponentTypeFromNodeType(nodeType: string): string {
  switch (nodeType) {
    case 'start':
      return '1'; // Api::COMPONENT_TYPE_START
    case 'subprocess':
      return '2'; // Api::COMPONENT_TYPE_SUBPROCESS  
    case 'element':
      return '4'; // Api::COMPONENT_TYPE_ELEMENT
    case 'link':
      return '5'; // Api::COMPONENT_TYPE_LINK
    case 'gateway':
      return '6'; // Api::COMPONENT_TYPE_GATEWAY
    default:
      return '4'; // Default to element
  }
}

/**
 * Parse model data from Drupal JSON format to React Flow format
 */
export function parseModelData(modelData: ModelData | string | null): { nodes: Node[], edges: Edge[], modelData: ModelData | null } {
  if (!modelData) {
    return { nodes: [], edges: [], modelData: null };
  }

  const data = typeof modelData === 'string' ? JSON.parse(modelData) : modelData;
  
  const nodes = (data.nodes || []).map((node: any) => {
    // Resolve the ReactFlow node type: prefer an explicit `type` string,
    // otherwise derive it from the integer `componentType` via the shared
    // type map.  This supports both the legacy format (with `type`) and
    // the new format where `convert()` only provides `componentType`.
    const nodeType = node.type || resolveNodeType(node.componentType);
    return {
      id: String(node.id), // Ensure ID is string
      type: nodeType,
      position: node.position || { x: LAYOUT.DEFAULT_POSITION_X, y: LAYOUT.DEFAULT_POSITION_Y },
      data: {
        label: node.label || node.id,
        plugin: node.plugin,
        configuration: node.configuration || {},
        componentType: node.componentType ?? getComponentTypeFromNodeType(nodeType),
        ...node,
      },
    };
  });

  // Create a set of valid node IDs for edge validation
  const nodeIds = new Set(nodes.map((n: any) => n.id));
  
  // Filter edges to only include those that reference existing nodes
  const edges = (data.edges || [])
    .filter((edge: any, _index: number) => {
      const sourceExists = nodeIds.has(String(edge.source));
      const targetExists = nodeIds.has(String(edge.target));
      return sourceExists && targetExists;
    })
    .map((edge: any, index: number) => {
      // Ensure unique edge ID
      const edgeId = edge.id || `${edge.source}-${edge.target}-${index}`;
      
      // Determine edge type based on content
      // Only two edge types: 'condition' (has a condition) or 'default' (no condition).
      // Annotations belong to conditions, not edges directly.
      const hasCondition = edge.condition || edge.conditionLabel ||
        (edge.conditionConfiguration && Object.keys(edge.conditionConfiguration).length > 0);
      const edgeType = hasCondition ? 'condition' : 'default';
      
      return {
        id: edgeId,
        source: String(edge.source),
        target: String(edge.target),
        sourceHandle: 'output',
        targetHandle: 'input',
        type: edgeType,
        animated: false,
        label: edge.conditionLabel || edge.condition || '',
        markerEnd: {
          type: 'arrow',
          width: EDGE_STYLING.ARROW_WIDTH,
          height: EDGE_STYLING.ARROW_HEIGHT,
          color: 'var(--modeler-color-edge-stroke)',
          strokeWidth: EDGE_STYLING.STROKE_WIDTH,
        },
        style: {
          stroke: 'var(--modeler-color-edge-stroke)',
          strokeWidth: EDGE_STYLING.STROKE_WIDTH,
        },
        pathOptions: {
          offset: EDGE_STYLING.CONTROL_OFFSET,
          borderRadius: EDGE_STYLING.BORDER_RADIUS,
        },
        data: {
          condition: edge.condition,
          controlOffset: edge.controlOffset || { x: 0, y: 0 }, // Add control point offset
          ...edge,
        },
      };
    });

  // Apply auto-layout if all nodes have the same position (indicating they need layout)
  const unlockedNodes = nodes;
  const needsLayout = unlockedNodes.length > 1 && unlockedNodes.every((n: any) => n.position.x === LAYOUT.DEFAULT_POSITION_X && n.position.y === LAYOUT.DEFAULT_POSITION_Y);
  if (needsLayout) {
    const layoutedNodes = autoLayout(nodes, edges);
    return { nodes: layoutedNodes || nodes, edges, modelData: data };
  }
  
  return { nodes, edges, modelData: data };
}

/**
 * Filter out internal properties (starting with _) from configuration
 */
function filterInternalProperties(config: Record<string, unknown> | undefined): Record<string, unknown> {
  if (!config) return {};
  return Object.fromEntries(
    Object.entries(config).filter(([key]) => !key.startsWith('_'))
  );
}

/**
 * Convert a label to snake_case for use as model ID
 */
export function labelToSnakeCase(label: string): string {
  return label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s_-]/g, '') // Remove special characters except spaces, underscores, hyphens
    .replace(/[\s-]+/g, '_')       // Replace spaces and hyphens with underscores
    .replace(/_+/g, '_')           // Collapse multiple underscores
    .replace(/^_|_$/g, '');        // Remove leading/trailing underscores
}

/**
 * Generate a model ID from label or create a unique fallback
 */
function generateModelId(metadata: Record<string, unknown>): string {
  // If ID is already provided, use it
  if (typeof metadata.id === 'string' && metadata.id) {
    return metadata.id;
  }

  // If label is provided, generate ID from it
  if (typeof metadata.label === 'string' && metadata.label && metadata.label !== 'Untitled Model') {
    const snakeCaseId = labelToSnakeCase(metadata.label);
    if (snakeCaseId) {
      return snakeCaseId;
    }
  }

  // Fallback to UUID
  return uuidv4();
}

/**
 * Export model data from React Flow format to Drupal JSON format
 */
export function exportModelData(nodes: Node[], edges: Edge[], metadata: Record<string, unknown> = {}) {
  const modelData = {
    id: generateModelId(metadata),
    version: (typeof metadata.version === 'string' ? metadata.version : undefined) || '1.0.0',
    metadata: {
      label: (typeof metadata.label === 'string' ? metadata.label : undefined) || 'Untitled Model',
      documentation: (typeof metadata.documentation === 'string' ? metadata.documentation : undefined) || '',
      executable: metadata.executable !== false,
      tags: (Array.isArray(metadata.tags) ? metadata.tags : undefined) || [],
      changelog: (typeof metadata.changelog === 'string' ? metadata.changelog : undefined) || '',
      ...metadata,
    },
    nodes: nodes.map(node => ({
      id: node.id,
      componentType: node.data.componentType,
      plugin: node.data.plugin,
      label: node.data.label,
      position: {
        x: Math.round(node.position.x),
        y: Math.round(node.position.y),
      },
      configuration: filterInternalProperties(node.data.configuration),
      annotation: node.data.annotation || '', // Include annotation in export
    })),
    edges: edges.map(edge => ({
      id: edge.id,
      source: edge.source,
      target: edge.target,
      sourceHandle: edge.sourceHandle,
      targetHandle: edge.targetHandle,
      condition: edge.data?.condition || edge.label || '',
      conditionId: edge.data?.conditionId || '', // Preserve original condition ID for round-trip stability
      conditionLabel: edge.data?.conditionLabel || '',
      conditionConfiguration: edge.data?.conditionConfiguration || {},
      annotation: edge.data?.annotation || '', // Include annotation in export
      controlOffset: edge.data?.controlOffset || { x: 0, y: 0 }, // Include control point offset
    })),
  };

  return modelData;
}

/**
 * Generate automatic layout for nodes based on edge flow
 * Refactored into smaller, maintainable functions
 */
export function autoLayout(nodes: Node[], edges: Edge[]): Node[] | null {
  // Safety checks
  if (!nodes || nodes.length === 0) return null;
  if (!edges) edges = []; // Default to empty array if edges is undefined
  
  const layoutNodes = [...nodes];

  // Default starting position for layout
  const startPos = { x: LAYOUT.LAYOUT_START_X, y: LAYOUT.LAYOUT_START_Y };

  // Build graph data structure
  const graphData = buildGraphData(layoutNodes, edges);

  // Find start nodes for layout
  const actualStartNodes = findStartNodes(layoutNodes, graphData.inDegree);

  // Process flow layout to determine row/column positions
  const nodePositions = processFlowLayout(actualStartNodes, layoutNodes, graphData);
  
  // Convert positions to actual coordinates (pass edges for condition-aware spacing)
  const nodesWithCoordinates = convertPositionsToCoordinates(
    layoutNodes,
    nodePositions,
    startPos,
    [],
    edges,
  );
  
  // Group nodes by row for optimization
  const rowNodes = groupNodesByRow(nodePositions, nodesWithCoordinates);
  
  // Optimize alignment within rows
  optimizeRowAlignment(nodesWithCoordinates, edges, rowNodes);
  
  return nodesWithCoordinates;
}

/**
 * Calculate bounds for a subset of nodes
 * @param {Array} nodes - Array of nodes to calculate bounds for
 * @returns {Object} Bounds object with x, y, width, height
 */
function getNodesBounds(nodes: Node[]): BoundingBox {
  if (!nodes || nodes.length === 0) {
    return { x: 0, y: 0, width: 0, height: 0 };
  }

  const nodeWidth = NODE_DIMENSIONS.DEFAULT_WIDTH; // Estimated width
  const nodeHeight = 80; // Estimated height (no specific constant for this)

  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;

  nodes.forEach((node: Node) => {
    const x = node.position?.x || 0;
    const y = node.position?.y || 0;
    const width = node.width || nodeWidth;
    const height = node.height || nodeHeight;
    
    minX = Math.min(minX, x);
    minY = Math.min(minY, y);
    maxX = Math.max(maxX, x + width);
    maxY = Math.max(maxY, y + height);
  });
  
  return {
    x: minX,
    y: minY,
    width: maxX - minX,
    height: maxY - minY
  };
}

/**
 * Calculate viewport for fitting specific nodes
 * @param {Array} nodes - Nodes to fit
 * @param {number} viewportWidth - Width of viewport
 * @param {number} viewportHeight - Height of viewport
 * @param {number} padding - Padding factor (0.1 = 10% padding)
 * @returns {Object} Viewport object with x, y, zoom
 */
export function getFitViewport(nodes: Node[], viewportWidth: number, viewportHeight: number, padding = 0.1): Viewport {
  const bounds = getNodesBounds(nodes);
  
  if (bounds.width === 0 || bounds.height === 0) {
    return { x: 0, y: 0, zoom: 1 };
  }
  
  // Calculate zoom to fit bounds with padding
  const paddingX = bounds.width * padding;
  const paddingY = bounds.height * padding;
  const zoomX = viewportWidth / (bounds.width + paddingX * 2);
  const zoomY = viewportHeight / (bounds.height + paddingY * 2);
  const zoom = Math.min(zoomX, zoomY, VIEWPORT.MAX_ZOOM);
  
  // Calculate position to center the bounds
  const x = -bounds.x * zoom + (viewportWidth - bounds.width * zoom) / 2;
  const y = -bounds.y * zoom + (viewportHeight - bounds.height * zoom) / 2;
  
  return { x, y, zoom };
}