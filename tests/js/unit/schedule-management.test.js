/**
 * schedule-management.js smoke tests
 */

global.window = global.window || { location: { href: 'http://localhost' } };
global.document = global.document || {
  addEventListener: jest.fn(),
  getElementById: jest.fn(() => null),
  querySelector: jest.fn(() => null),
  querySelectorAll: jest.fn(() => []),
  createElement: jest.fn(() => ({
    classList: { add: jest.fn(), remove: jest.fn() },
    appendChild: jest.fn(),
    addEventListener: jest.fn(),
    dataset: {},
    style: {}
  })),
  body: { appendChild: jest.fn() }
};

describe('schedule-management.js', () => {
  test('module loads without throwing', () => {
    expect(() => require('@js/schedule/schedule-management.js')).not.toThrow();
  });
});
