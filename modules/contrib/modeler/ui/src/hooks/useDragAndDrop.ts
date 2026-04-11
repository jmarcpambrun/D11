/**
 * Custom hook for drag and drop functionality in the modeler
 * Handles dropping components onto the canvas and condition drag-and-drop on edges
 */

import { useCallback, useState } from 'react';
import { useReactFlow } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { generateNodeId } from '../utils/clipboardUtils';
import { getEdgeTypeWithCondition } from '../utils/edgeTypeUtils';
import { findNearestEdge } from '../utils/layoutHelpers';
import { autoLayout } from '../utils/modelUtils';

interface UseDragAndDropProps {
  isLocked: boolean;
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useDragAndDrop({
  isLocked,
  setHasUnsavedChanges,
  saveHistory
}: UseDragAndDropProps) {
  const { screenToFlowPosition } = useReactFlow();
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);
  // Drag state
  const [isDraggingCondition, setIsDraggingCondition] = useState(false);
  const [hoveredDropEdge, setHoveredDropEdge] = useState<Edge | null>(null);

  // Wrapper around the extracted findNearestEdge utility for use in callbacks
  const findNearestEdgeForDrop = useCallback((position: { x: number; y: number }, maxDistance = 80, currentEdges?: Edge[], currentNodes?: Node[]): Edge | null => {
    return findNearestEdge(position, currentEdges || edges, currentNodes || nodes, maxDistance);
  }, [edges, nodes]);

  const onDrop = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    
    // Prevent dropping components when canvas is locked
    if (isLocked) {
      return;
    }

    if (saveHistory) saveHistory();
    
    const type = event.dataTransfer.getData('application/reactflow');
    const plugin = event.dataTransfer.getData('application/plugin');
    const componentData = JSON.parse(event.dataTransfer.getData('component') || '{}');
    
    if (type && plugin) {
      const position = screenToFlowPosition({
        x: event.clientX,
        y: event.clientY,
      });

      // Special handling for condition/decision components (type === 'link')
      if (type === 'link') {
        const nearestEdge = findNearestEdgeForDrop(position);
        
        if (!nearestEdge) {
          // Show error message - no edge nearby
          alert('Decision components can only be attached to existing connections. Please drop near a connection line.');
          setIsDraggingCondition(false);
          setHoveredDropEdge(null);
          return;
        }

        // Attach condition to the edge
        const conditionLabel = componentData.label || plugin.split('.').pop() || plugin;

        // Build the updated edges with the condition data applied.
        const updatedEdges = edges.map(edge => {
          if (edge.id === nearestEdge.id) {
            return {
              ...edge,
              label: conditionLabel,
              selected: true,
              data: {
                ...edge.data,
                condition: plugin,
                conditionLabel: conditionLabel
              },
              type: getEdgeTypeWithCondition(plugin, edge.data)
            };
          }
          return edge.selected ? { ...edge, selected: false } : edge;
        });

        // Re-run auto-layout so the condition-aware spacing algorithm positions
        // all nodes cleanly, rather than naively shifting descendants.
        const deselectedNodes = nodes.some(n => n.selected)
          ? nodes.map(n => n.selected ? { ...n, selected: false } : n)
          : nodes;
        const layoutedNodes = autoLayout(deselectedNodes, updatedEdges);
        if (layoutedNodes) {
          setNodes(layoutedNodes);
        }

        setEdges(updatedEdges);
        
        setHasUnsavedChanges(true);
        setIsDraggingCondition(false);
        setHoveredDropEdge(null);
        return;
      }

      // Regular node creation for non-condition components
      const label = componentData.label || plugin.split('.').pop() || plugin;
      const nodeType = type === 'start' ? 'start' : (type === 'gateway' ? 'gateway' : 'element');
      const id = generateNodeId(label, nodeType);
      const newNode = {
        id,
        type: nodeType,
        position,
        selected: true,
        data: {
          plugin,
          label,
          componentType: componentData.componentType,
          description: componentData.description,
          documentationUrl: componentData.documentationUrl,
        },
      };

      // Deselect all existing nodes/edges and add the new node as selected.
      // ReactFlow's onSelectionChange will update the store's selection state.
      setNodes(prev => [...prev.map(n => n.selected ? { ...n, selected: false } : n), newNode]);
      setEdges(prev => {
        const hasSelected = prev.some(e => e.selected);
        return hasSelected ? prev.map(e => e.selected ? { ...e, selected: false } : e) : prev;
      });
      
      setHasUnsavedChanges(true);
    }
    
    // Reset drag states
    setIsDraggingCondition(false);
    setHoveredDropEdge(null);
  }, [isLocked, screenToFlowPosition, nodes, edges, setNodes, setEdges, findNearestEdgeForDrop, setHasUnsavedChanges, setIsDraggingCondition, setHoveredDropEdge, saveHistory]);

  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    
    if (screenToFlowPosition) {
      const position = screenToFlowPosition({
        x: event.clientX,
        y: event.clientY,
      });
      
      // If dragging a condition, provide visual feedback for edges
      if (isDraggingCondition) {
        const nearestEdge = findNearestEdgeForDrop(position);
        
        // Update hovered drop edge state - allow replacement on edges with conditions
        const validDropEdge = nearestEdge || null;
        if (hoveredDropEdge?.id !== validDropEdge?.id) {
          setHoveredDropEdge(validDropEdge);
        }
      }
    }
  }, [isDraggingCondition, screenToFlowPosition, findNearestEdgeForDrop, hoveredDropEdge, setHoveredDropEdge]);

  return {
    // Event handlers
    onDrop,
    onDragOver,
    
    // State
    isDraggingCondition,
    hoveredDropEdge,
    
    // Helper functions
    findNearestEdge: findNearestEdgeForDrop
  };
}