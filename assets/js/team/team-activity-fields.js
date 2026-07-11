/** team-activity-fields.js */
(function () {
            function aiduniteSyncActivityAreaHidden() {
                var sync = document.getElementById('activity_area_sync');
                var typeEl = document.getElementById('activity_area_type');
                var wardEl = document.getElementById('activity_area_ward');
                var cityEl = document.getElementById('activity_area_city');
                var t = typeEl ? typeEl.value : '';
                if (t === 'tokyo_ward' && wardEl) {
                    if (sync) sync.value = wardEl.value || '';
                } else if (t === 'tokyo_city' && cityEl) {
                    if (sync) sync.value = cityEl.value || '';
                } else if (sync) {
                    sync.value = '';
                }
            }
            function aiduniteSyncActivityTokyo() {
                var pref = document.getElementById('activity_prefecture');
                var wrap = document.getElementById('aidunite-team-activity-tokyo-wrap');
                var type = document.getElementById('activity_area_type');
                var wardEl = document.getElementById('activity_area_ward');
                var cityEl = document.getElementById('activity_area_city');
                if (!pref || !wrap) return;
                var isTokyo = pref.value === '東京都';
                var typeCell = document.getElementById('team-reg-tokyo-type-cell');
                if (typeCell) {
                    typeCell.style.display = isTokyo ? '' : 'none';
                }
                wrap.style.display = isTokyo ? '' : 'none';
                var wardWrap = document.querySelector('.aidunite-team-activity-tokyo-ward-wrap');
                var cityWrap = document.querySelector('.aidunite-team-activity-tokyo-city-wrap');
                if (!isTokyo && type) type.value = '';
                var t = type ? type.value : '';
                if (wardWrap) wardWrap.style.display = (isTokyo && t === 'tokyo_ward') ? '' : 'none';
                if (cityWrap) cityWrap.style.display = (isTokyo && t === 'tokyo_city') ? '' : 'none';
                if (wardEl) {
                    wardEl.required = isTokyo && t === 'tokyo_ward';
                    if (isTokyo && t === 'tokyo_ward') {
                        wardEl.setAttribute('name', 'activity_area');
                    } else {
                        wardEl.removeAttribute('name');
                    }
                }
                if (cityEl) {
                    cityEl.required = isTokyo && t === 'tokyo_city';
                    if (isTokyo && t === 'tokyo_city') {
                        cityEl.setAttribute('name', 'activity_area_city');
                    } else {
                        cityEl.removeAttribute('name');
                    }
                }
                aiduniteSyncActivityAreaHidden();
            }
            document.addEventListener('change', function (e) {
                if (!e.target) return;
                if (e.target.id === 'activity_prefecture' || e.target.id === 'activity_area_type'
                    || e.target.id === 'activity_area_ward' || e.target.id === 'activity_area_city') {
                    aiduniteSyncActivityTokyo();
                }
            });
            document.addEventListener('DOMContentLoaded', aiduniteSyncActivityTokyo);
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || !form.querySelector || !form.querySelector('#activity_prefecture')) {
                    return;
                }
                aiduniteSyncActivityAreaHidden();
            }, true);
        })();
