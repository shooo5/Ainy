/** schedule-form-template.js */
document.getElementById('matching-request').addEventListener('change', function () {
  const checked = this.checked;
  document.getElementById('gender-condition-wrap').style.display = checked ? 'block' : 'none';
  document.getElementById('place-condition-wrap').style.display = checked ? 'block' : 'none';
});

document.getElementById('schedule-form').addEventListener('submit', function(e) {
  e.preventDefault();

  const startDate = new Date(document.getElementById('start-date').value);
  const endDate = new Date(document.getElementById('end-date').value);
  const startTime = `${document.getElementById('start-hour').value}:${document.getElementById('start-minute').value}`;
  const endTime = `${document.getElementById('end-hour').value}:${document.getElementById('end-minute').value}`;
  const place = document.getElementById('place').value;
  const note = document.getElementById('note').value;
  const scheduleType = document.getElementById('schedule-type').value;
  const matchFlag = document.getElementById('matching-request').checked;
  const genderCondition = document.getElementById('gender-condition')?.value || '';
  const placeCondition = document.getElementById('place-condition')?.value || '';

  const selectedWeekdays = Array.from(document.querySelectorAll('input[name="weekdays"]:checked')).map(cb => parseInt(cb.value));

  const datesToRegister = [];
  let d = new Date(startDate);
  while (d <= endDate) {
    if (selectedWeekdays.includes(d.getDay())) {
      datesToRegister.push(new Date(d));
    }
    d.setDate(d.getDate() + 1);
  }

  if (datesToRegister.length === 0) {
    document.getElementById('result').textContent = '⚠ 該当する日付がありません。';
    return;
  }

  let html = `<p>以下の ${datesToRegister.length} 件を登録予定：</p><ul>`;
  datesToRegister.forEach(date => {
    html += `<li>${date.toLocaleDateString()} ${startTime}〜${endTime}（${scheduleType}）`;
    if (matchFlag) html += `【マッチング希望】`;
    html += `</li>`;
  });
  html += '</ul>';
  document.getElementById('result').innerHTML = html;

  const [startHour, startMinute] = startTime.split(':');
  const [endHour, endMinute] = endTime.split(':');
  const apiUrl = '/wp-json/aidunite/v1/register-schedule-v2';
  const headers = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  };

  (async () => {
    let successCount = 0;
    let lastError = '';

    for (const date of datesToRegister) {
      const payload = {
        start_date: date.toISOString().split('T')[0],
        start_hour: startHour,
        start_minute: startMinute,
        end_hour: endHour,
        end_minute: endMinute,
        schedule_type: scheduleType,
        intent: matchFlag ? 'recruit' : 'confirmed',
        venue_condition: placeCondition,
        venue_name: place,
        gender_condition: genderCondition,
        note: note,
        male_teams: matchFlag && genderCondition === 'male' ? 1 : 0,
        female_teams: matchFlag && genderCondition === 'female' ? 1 : 0
      };

      try {
        const response = await fetch(apiUrl, {
          method: 'POST',
          headers,
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data && data.success) {
          successCount++;
        } else {
          lastError = (data && data.message) ? data.message : '登録に失敗しました';
        }
      } catch (err) {
        console.error(err);
        lastError = '通信エラーが発生しました';
        break;
      }
    }

    if (successCount > 0) {
      document.getElementById('result').innerHTML += `<p>✅ ${successCount}件のスケジュールを登録しました！</p>`;

      let countdown = 2;
      const countdownElement = document.createElement('p');
      countdownElement.innerHTML = `<p>⏰ ${countdown}秒後にスケジュール一覧ページに移動します...</p>`;
      document.getElementById('result').appendChild(countdownElement);

      const timer = setInterval(() => {
        countdown--;
        countdownElement.innerHTML = `<p>⏰ ${countdown}秒後にスケジュール一覧ページに移動します...</p>`;
        if (countdown <= 0) {
          clearInterval(timer);
          window.location.replace('/my-schedule');
        }
      }, 1000);
    } else {
      document.getElementById('result').innerHTML += `<p>❌ ${lastError || 'エラーが発生しました。'}</p>`;
    }
  })();
});
