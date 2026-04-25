<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Отзывы и обратная связь</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
  <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/css/materialize.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="shortcut icon" href="/swad/static/img/DD.svg" type="image/x-icon">
  <link href="assets/css/custom.css" rel="stylesheet" type="text/css" />
  <style>
    .reviews-wrap {
      margin-top: 8px;
    }

    .reviews-collapsible .collapsible-header {
      padding: 10px 14px;
    }

    .reviews-collapsible .collapsible-body {
      padding: 10px 14px;
    }

    .review-item {
      padding: 10px 0;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
    }

    .review-item:last-child {
      border-bottom: 0;
    }

    .review-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 6px;
    }

    .review-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      font-size: 12px;
      color: #666;
    }

    .review-meta b {
      color: #333;
      font-weight: 600;
    }

    .review-score {
      font-weight: 700;
      font-size: 12px;
      padding: 2px 8px;
      border-radius: 999px;
      background: rgba(38, 166, 154, .12);
      color: #1b7f75;
    }

    .review-text {
      margin: 0;
      font-size: 14px;
      line-height: 1.35;
      white-space: pre-wrap;
      color: #222;
    }

    .review-actions .btn {
      height: 28px;
      line-height: 28px;
      padding: 0 10px;
      font-size: 12px;
    }

    .reply-box {
      margin-top: 8px;
      padding: 8px 10px;
      border-radius: 10px;
      background: rgba(0, 150, 136, .08);
      border: 1px solid rgba(0, 150, 136, .18);
      font-size: 13px;
      color: #1c5e58;
      white-space: pre-wrap;
    }

    .reply-box .reply-head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      font-size: 12px;
      color: #356b66;
      margin-bottom: 4px;
    }

    /* меньше воздуха в модалке */
    .modal.compact-modal {
      max-height: 85%;
    }

    .compact-modal .modal-content {
      padding: 16px 16px 6px;
    }

    .compact-modal .modal-footer {
      padding: 6px 10px;
      height: auto;
    }

    .compact-modal .card-panel {
      margin: 10px 0;
      padding: 10px;
    }

    .compact-modal textarea.materialize-textarea {
      margin-bottom: 0;
    }
  </style>

</head>

<body>
  <?php
  require_once('../swad/static/elements/sidebar.php');

  /** ВАЖНО: убедись что $db уже создан в sidebar.php или подключи config здесь */
  $stmt = $db->connect()->prepare("SELECT id, name FROM games WHERE developer = ? ORDER BY id DESC");
  $stmt->execute([$_SESSION['studio_id']]);
  $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $projectIds = array_column($projects, 'id');

  $reviewsByGame = [];
  if (!empty($projectIds)) {
    $in = implode(',', array_fill(0, count($projectIds), '?'));

    $sql = "
    SELECT 
      r.id AS review_id,
      r.game_id,
      r.rating,
      r.text AS review_text,
      r.created_at,
      u.username AS author_nick,
      rr.text AS reply_text,
      rr.created_at AS reply_created_at
    FROM game_reviews r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN review_replies rr ON rr.review_id = r.id AND rr.studio_id = ?
    WHERE r.game_id IN ($in)
    ORDER BY r.game_id DESC, r.created_at DESC
  ";

    $params = array_merge([$_SESSION['studio_id']], $projectIds);
    $stmt = $db->connect()->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $reviewsByGame[(int)$row['game_id']][] = $row;
    }
  }
  ?>

  <main>
    <section class="content">
      <div class="page-announce valign-wrapper">
        <a href="#" data-activates="slide-out" class="button-collapse valign hide-on-large-only">
          <i class="material-icons">menu</i>
        </a>
        <h1 class="page-announce-text valign">// Отзывы на игры</h1>
      </div>

      <div class="container">
        <div class="row reviews-wrap">
          <div class="col s12">
            <ul class="collapsible popout reviews-collapsible">
              <?php foreach ($projects as $p):
                $gameId = (int)$p['id'];
                $reviews = $reviewsByGame[$gameId] ?? [];
              ?>
                <li>
                  <div class="collapsible-header">
                    <i class="material-icons">games</i>
                    <span style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="new badge" data-badge-caption=""><?= count($reviews) ?></span>
                  </div>

                  <div class="collapsible-body">
                    <?php if (empty($reviews)): ?>
                      <span class="grey-text" style="font-size:13px;">Пока нет отзывов.</span>
                    <?php else: ?>

                      <?php foreach ($reviews as $r): ?>
                        <div class="review-item">
                          <div class="review-top">
                            <div class="review-meta">
                              <b><?= htmlspecialchars($r['author_nick'] ?? 'unknown') ?></b>
                              <span class="review-score"><?= (int)$r['rating'] ?>/10</span>
                              <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?></span>
                            </div>

                            <div class="review-actions">
                              <a href="#replyModal"
                                class="btn teal modal-trigger"
                                data-review-id="<?= (int)$r['review_id'] ?>"
                                data-game-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                data-author="<?= htmlspecialchars($r['author_nick'] ?? 'unknown', ENT_QUOTES) ?>"
                                data-text="<?= htmlspecialchars(mb_strimwidth($r['review_text'], 0, 220, '…'), ENT_QUOTES) ?>">
                                Ответить
                              </a>
                            </div>
                          </div>

                          <p class="review-text"><?= htmlspecialchars($r['review_text']) ?></p>

                          <?php if (!empty($r['reply_text'])): ?>
                            <div class="reply-box">
                              <div class="reply-head">
                                <span><b>Ваш ответ</b></span>
                                <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['reply_created_at']))) ?></span>
                              </div>
                              <?= htmlspecialchars($r['reply_text']) ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>

                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php require_once('footer.php'); ?>

  <div id="replyModal" class="modal compact-modal" style="border-radius:16px;">
    <div class="modal-content">
      <h6 style="margin:0 0 8px; font-weight:700;">Ответ на отзыв</h6>

      <div class="card-panel grey lighten-5" style="border-radius:12px;">
        <div style="font-size:12px; color:#666;">
          <b id="rmGameName">—</b> • <span id="rmAuthor">—</span>
        </div>
        <div id="rmText" class="grey-text" style="margin-top:6px; font-size:13px; white-space:pre-wrap;">—</div>
      </div>

      <form id="replyForm" onsubmit="return false;">
        <input type="hidden" name="review_id" id="rmReviewId" value="">
        <div class="input-field" style="margin-top:10px;">
          <textarea id="rmReply" name="reply" class="materialize-textarea" maxlength="2000" required></textarea>
          <label for="rmReply">Ваш ответ</label>
        </div>
      </form>
    </div>

    <div class="modal-footer">
      <a href="#!" class="modal-close btn-flat">Отмена</a>
      <button type="button" id="rmSend" class="btn teal">Отправить</button>
    </div>
  </div>


  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.98.0/js/materialize.min.js"></script>

  <script>
    // Совместимые хелперы под Materialize 0.98 и 1.x
    function toastCompat(msg) {
      if (window.M && typeof M.toast === 'function') return M.toast({
        html: msg
      });
      if (window.Materialize && typeof Materialize.toast === 'function') return Materialize.toast(msg, 3000);
      alert(msg);
    }

    function updateTextFieldsCompat() {
      if (window.M && typeof M.updateTextFields === 'function') return M.updateTextFields();
      if (window.Materialize && typeof Materialize.updateTextFields === 'function') return Materialize.updateTextFields();
    }

    function closeModalCompat(selector) {
      // Materialize 0.98
      try {
        $(selector).modal('close');
        return;
      } catch (e) {}
      // Materialize 1.x fallback
      if (window.M && M.Modal) {
        const el = document.querySelector(selector);
        const inst = M.Modal.getInstance(el);
        if (inst) inst.close();
      }
    }

    $(function() {
      // Инициализация компонентов (один раз)
      $('.button-collapse').sideNav({
        menuWidth: 300,
        edge: 'left',
        closeOnClick: false,
        draggable: true
      });

      $('.tooltipped').tooltip({
        delay: 50
      });
      $('.collapsible').collapsible({
        accordion: true
      });
      $('.modal').modal();

      // Открыть модалку и заполнить
      $(document).on('click', 'a.modal-trigger', function() {
        const reviewId = $(this).data('review-id');
        const gameName = $(this).data('game-name');
        const author = $(this).data('author');
        const text = $(this).data('text');

        $('#rmReviewId').val(reviewId);
        $('#rmGameName').text(gameName);
        $('#rmAuthor').text(author);
        $('#rmText').text(text);

        $('#rmReply').val('');
        updateTextFieldsCompat();
        $('#rmReply').trigger('autoresize');
      });

      // Отправка ответа
      $('#rmSend').on('click', function() {
        const reviewId = $('#rmReviewId').val();
        const reply = ($('#rmReply').val() || '').trim();

        if (!reply) {
          toastCompat('Введите текст ответа');
          return;
        }

        const $btn = $('#rmSend');
        $btn.prop('disabled', true).text('Отправка...');

        fetch('reply_review.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              review_id: reviewId,
              reply: reply
            })
          })
          .then(r => r.json())
          .then(data => {
            if (data && data.success) {
              toastCompat('Ответ сохранён');
              closeModalCompat('#replyModal');
              setTimeout(() => location.reload(), 300);
            } else {
              toastCompat((data && data.message) ? data.message : 'Ошибка');
            }
          })
          .catch(() => {
            toastCompat('Ошибка сети');
          })
          .finally(() => {
            $btn.prop('disabled', false).text('Отправить');
          });
      });
    });
  </script>

</body>

</html>