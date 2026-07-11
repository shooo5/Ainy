/**
 * match-actions.js smoke tests
 */

global.window = global.window || { location: { origin: 'http://localhost', href: 'http://localhost', reload: jest.fn() } };
global.window.wpApiSettings = { nonce: 'test_nonce' };
global.document = global.document || {
  addEventListener: jest.fn(),
  getElementById: jest.fn(() => null),
  querySelectorAll: jest.fn(() => []),
  body: { appendChild: jest.fn() }
};
global.fetch = jest.fn(() => Promise.resolve({ json: () => Promise.resolve({ success: true }) }));

describe('match-actions.js', () => {
  test('module loads without throwing', () => {
    expect(() => require('@js/match/match-actions.js')).not.toThrow();
  });
});
