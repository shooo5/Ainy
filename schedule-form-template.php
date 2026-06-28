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

<?php
$schedule_form_js = get_stylesheet_directory() . '/assets/js/schedule/schedule-form-template.js';
if (is_readable($schedule_form_js)) {
    wp_enqueue_script(
        'aidunite-schedule-form-template',
        get_stylesheet_directory_uri() . '/assets/js/schedule/schedule-form-template.js',
        [],
        (string) filemtime($schedule_form_js),
        true
    );
}
