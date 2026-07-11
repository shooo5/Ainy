/**
 * match-board-button-control.js smoke tests
 */

global.jQuery = global.jQuery || jest.fn(() => ({
  ready: (cb) => { if (typeof cb === 'function') cb(); },
  on: jest.fn(),
  off: jest.fn(),
  prop: jest.fn(),
  data: jest.fn(),
  val: jest.fn(() => 'test-nonce')
}));
global.$ = global.jQuery;
global.ajaxurl = '/wp-admin/admin-ajax.php';
global.window = global.window || { location: { href: 'http://localhost' } };
global.document = global.document || {
  addEventListener: jest.fn(),
  querySelector: jest.fn(() => null),
  querySelectorAll: jest.fn(() => []),
  getElementById: jest.fn(() => null),
};

describe('match-board-button-control.js', () => {
  test('module loads without throwing', () => {
    expect(() => require('@js/match/match-board-button-control.js')).not.toThrow();
  });
});
