/**
 * テーマ SVG アイコン（PHP が wp_localize_script で渡す svgMap を利用）
 */
(function (global) {
    'use strict';

    function getMap() {
        return (global.aiduniteThemeIcons && global.aiduniteThemeIcons.svgMap) || {};
    }

    function getScheduleTypeMap() {
        return (global.aiduniteThemeIcons && global.aiduniteThemeIcons.scheduleTypeIcons) || {};
    }

    function applySize(svg, size) {
        if (!svg || !size) {
            return svg;
        }
        var s = String(size);
        var out = svg.replace(/\swidth="[^"]*"/, ' width="' + s + '"');
        out = out.replace(/\sheight="[^"]*"/, ' height="' + s + '"');
        return out;
    }

    var AidUniteThemeIcons = {
        getSvg: function (basename) {
            if (!basename) {
                return '';
            }
            return getMap()[basename] || '';
        },

        html: function (basename, size, options) {
            var svg = this.getSvg(basename);
            if (!svg) {
                return '';
            }
            if (size) {
                svg = applySize(svg, size);
            }
            var isBlock = options && options.block === true;
            var inlineClass = isBlock ? '' : ' aidunite-icon--inline';
            var modifierClass = options && options.modifierClass
                ? ' aidunite-icon--' + options.modifierClass
                : '';
            return '<span class="aidunite-icon aidunite-icon--' + basename + modifierClass + inlineClass + '" aria-hidden="true">' + svg + '</span>';
        },

        setHtml: function (element, basename, size) {
            if (!element) {
                return;
            }
            element.innerHTML = this.html(basename, size);
        },

        getScheduleIconBasename: function (type) {
            var map = getScheduleTypeMap();
            if (!type) {
                return 'calendar_month';
            }
            if (map[type]) {
                return map[type];
            }
            var keys = Object.keys(map);
            for (var i = 0; i < keys.length; i++) {
                var key = keys[i];
                if (type.indexOf(key) !== -1 || key.indexOf(type) !== -1) {
                    return map[key];
                }
            }
            return 'calendar_month';
        },

        scheduleIconHtml: function (type, size) {
            return this.html(this.getScheduleIconBasename(type), size || 20);
        }
    };

    global.AidUniteThemeIcons = AidUniteThemeIcons;
})(typeof window !== 'undefined' ? window : global);
