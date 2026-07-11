/**
 * profile-edit.js smoke tests
 */

global.window = global.window || { location: { href: 'http://localhost' } };
global.document = global.document || {
  addEventListener: jest.fn(),
  getElementById: jest.fn(() => null),
  querySelector: jest.fn(() => null),
  querySelectorAll: jest.fn(() => []),
  createElement: jest.fn(() => ({ classList: { add: jest.fn(), remove: jest.fn() }, style: {}, appendChild: jest.fn() })),
  body: { appendChild: jest.fn() }
};

describe('profile-edit.js', () => {
  test('module loads without throwing', () => {
    expect(() => require('@js/pages/profile-edit.js')).not.toThrow();
  });
});
