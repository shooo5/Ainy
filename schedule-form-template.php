<?php
/*--------------------------------------------------------------
  No.22 練習スケジュール登録テンプレート（時間：10分刻み select形式 + マッチング希望UI）
  ファイル：schedule-form-template.php
--------------------------------------------------------------*/
?>
<div class="schedule-form-wrapper">
  <form id="schedule-form">
    <h2>🗓 練習スケジュール登録</h2>

    <label>開始日:</label>
    <input type="date" id="start-date" required>
    <label>終了日:</label>
    <input type="date" id="end-date" required><br><br>

    <label>開始時間:</label>
    <select id="start-hour" required>
      <?php for ($h = 0; $h < 24; $h++): ?>
        <option value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?></option>
      <?php endfor; ?>
    </select>
    :
    <select id="start-minute" class="minute-select" required>
      <?php foreach ([0, 10, 20, 30, 40, 50] as $m): ?>
        <option value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?></option>
      <?php endforeach; ?>
    </select>

    <label>終了時間:</label>
    <select id="end-hour" required>
      <?php for ($h = 0; $h < 24; $h++): ?>
        <option value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?></option>
      <?php endfor; ?>
    </select>
    :
    <select id="end-minute" class="minute-select" required>
      <?php foreach ([0, 10, 20, 30, 40, 50] as $m): ?>
        <option value="<?= sprintf('%02d', $m) ?>"><?= sprintf('%02d', $m) ?></option>
      <?php endforeach; ?>
    </select>
    <br><br>

    <label>対象曜日:</label><br>
    <label><input type="checkbox" name="weekdays" value="1">月</label>
    <label><input type="checkbox" name="weekdays" value="2">火</label>
    <label><input type="checkbox" name="weekdays" value="3">水</label>
    <label><input type="checkbox" name="weekdays" value="4">木</label>
    <label><input type="checkbox" name="weekdays" value="5">金</label>
    <label><input type="checkbox" name="weekdays" value="6">土</label>
    <label><input type="checkbox" name="weekdays" value="0">日</label><br><br>

    <label>活動種別:</label>
    <select id="schedule-type">
      <option value="練習">練習</option>
        <option value="練習試合">練習試合</option>
        <option value="公式試合">公式試合</option>
        <option value="休み">休み</option>
        <option value="合同練習">合同練習</option>
        <option value="ミーティング">ミーティング</option>
        <option value="未定">未定</option>
    </select><br><br>

    <label>会場:</label>
    <input type="text" id="place"><br>
    <label>備考:</label>
    <textarea id="note"></textarea><br>

    <label>
      <input type="checkbox" id="matching-request">
      練習試合の相手を探している（マッチング希望）
    </label><br><br>

    <!-- ② 男女条件 -->
    <div id="gender-condition-wrap" style="display: none;">
      <label>対戦条件（性別）:</label>
      <select id="gender-condition">
        <option value="male">男子</option>
        <option value="female">女子</option>
        <option value="both">男子・女子可</option>
      </select><br><br>
    </div>

    <!-- ③ 会場条件 -->
    <div id="place-condition-wrap" style="display: none;">
      <label>会場の条件:</label>
      <select id="place-condition">
        <option value="away">アウェイ</option>
        <option value="home">ホーム</option>
        <option value="both">どちらでも可</option>
      </select><br><br>
    </div>

    <button type="submit">✅ 該当日をまとめて登録</button>
    <div id="result"></div>


<div id="matching-conditions" style="display: none;">
  <label>性別条件:</label>
  <select id="matching-gender-condition">
    <option value="both">男子・女子可</option>
    <option value="male">男子</option>
    <option value="female">女子</option>
  </select><br><br>

  <label>会場条件:</label>
  <select id="schedule-place-option">
    <option value="both">どちらでも可</option>
    <option value="away">アウェイ</option>
    <option value="home">ホーム</option>
  </select><br><br>
</div>

</form>
</div>

<script>
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
</script>
