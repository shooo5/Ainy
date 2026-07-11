/**
 * schedule-edit.js smoke tests
 */

global.window = global.window || { location: { href: 'http://localhost' }, history: { pushState: jest.fn(), replaceState: jest.fn() } };
global.document = global.document || {
  addEventListener: jest.fn(),
  getElementById: jest.fn(() => null),
  querySelector: jest.fn(() => null),
  querySelectorAll: jest.fn(() => []),
  createElement: jest.fn(() => ({ classList: { add: jest.fn(), remove: jest.fn() }, style: {}, appendChild: jest.fn() })),
  body: { appendChild: jest.fn() }
};

global.fetch = jest.fn(() => Promise.resolve({ json: () => Promise.resolve({ success: true }) }));

describe('schedule-edit.js', () => {
  test('module loads without throwing', () => {
    expect(() => require('@js/schedule/schedule-edit.js')).not.toThrow();
  });
});
