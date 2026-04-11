/**
 * Tests for edgeTypeUtils
 */

import { getEdgeType, getEdgeTypeWithCondition } from '../edgeTypeUtils';

describe('edgeTypeUtils', () => {
  describe('getEdgeType', () => {
    it('should return "default" for null data', () => {
      expect(getEdgeType(null)).toBe('default');
    });

    it('should return "default" for undefined data', () => {
      expect(getEdgeType(undefined)).toBe('default');
    });

    it('should return "default" for empty data', () => {
      expect(getEdgeType({})).toBe('default');
    });

    it('should return "condition" when data has condition', () => {
      expect(getEdgeType({ condition: 'some-plugin' })).toBe('condition');
    });

    it('should return "condition" when data has conditionLabel', () => {
      expect(getEdgeType({ conditionLabel: 'My Condition' })).toBe('condition');
    });

    it('should return "condition" when data has conditionConfiguration', () => {
      expect(getEdgeType({ conditionConfiguration: { key: 'value' } })).toBe('condition');
    });

    it('should return "default" when data has only annotation (no condition)', () => {
      // Annotations belong to conditions, not edges. An edge with only an annotation is 'default'.
      expect(getEdgeType({ annotation: 'Some note' })).toBe('default');
    });

    it('should return "condition" when both condition and annotation exist', () => {
      expect(getEdgeType({ condition: 'plugin', annotation: 'note' })).toBe('condition');
    });

    it('should return "default" when all condition fields are null/empty', () => {
      expect(getEdgeType({ condition: null, conditionLabel: null, conditionConfiguration: null, annotation: null })).toBe('default');
    });

    it('should return "default" when condition is empty string', () => {
      expect(getEdgeType({ condition: '' })).toBe('default');
    });

    it('should return "default" when conditionLabel is empty string', () => {
      expect(getEdgeType({ conditionLabel: '' })).toBe('default');
    });

    it('should return "default" when conditionConfiguration is an empty object', () => {
      expect(getEdgeType({ conditionConfiguration: {} })).toBe('default');
    });

    it('should return "default" when conditionConfiguration is null', () => {
      expect(getEdgeType({ conditionConfiguration: null })).toBe('default');
    });

    it('should return "default" when all condition fields are empty strings/objects', () => {
      // Regression: backend sends condition: "", conditionLabel: "", conditionConfiguration: {}
      // for edges without conditions. All of these must be treated as "no condition".
      expect(getEdgeType({ condition: '', conditionLabel: '', conditionConfiguration: {} })).toBe('default');
    });

    it('should return "default" when all condition fields are null', () => {
      expect(getEdgeType({ condition: null, conditionLabel: null, conditionConfiguration: null })).toBe('default');
    });
  });

  describe('getEdgeTypeWithCondition', () => {
    it('should return "condition" when newCondition is truthy', () => {
      expect(getEdgeTypeWithCondition('plugin-id', {})).toBe('condition');
    });

    it('should return "condition" when newCondition is null but existingData has condition', () => {
      expect(getEdgeTypeWithCondition(null, { condition: 'existing' })).toBe('condition');
    });

    it('should return "default" when no conditions but annotation exists', () => {
      // Annotations belong to conditions; without a condition the edge is 'default'.
      expect(getEdgeTypeWithCondition(null, { annotation: 'note' })).toBe('default');
    });

    it('should return "default" when no condition and no annotation', () => {
      expect(getEdgeTypeWithCondition(null, {})).toBe('default');
    });

    it('should return "default" when all values are null/undefined', () => {
      expect(getEdgeTypeWithCondition(null, null)).toBe('default');
    });

    it('should return "default" when newCondition is undefined', () => {
      expect(getEdgeTypeWithCondition(undefined, undefined)).toBe('default');
    });

    it('should return "condition" when newCondition is truthy and annotation also exists', () => {
      expect(getEdgeTypeWithCondition('plugin', { annotation: 'note' })).toBe('condition');
    });

    it('should return "condition" when existingData has conditionConfiguration', () => {
      expect(getEdgeTypeWithCondition(null, { conditionConfiguration: { key: 'val' } })).toBe('condition');
    });

    it('should return "default" when existingData has empty conditionConfiguration', () => {
      expect(getEdgeTypeWithCondition(null, { conditionConfiguration: {} })).toBe('default');
    });

    it('should return "default" when all existing fields are empty strings/objects', () => {
      // Regression: matches backend data format where absent conditions use empty values.
      expect(getEdgeTypeWithCondition(null, {
        condition: '',
        conditionLabel: '',
        conditionConfiguration: {},
      })).toBe('default');
    });

    it('should return "default" when newCondition is empty string', () => {
      expect(getEdgeTypeWithCondition('', {})).toBe('default');
    });
  });
});
