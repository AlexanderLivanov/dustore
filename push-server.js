const express = require("express");
const webpush = require("web-push");
const fs = require("fs");
require("dotenv").config();

const app = express();
app.use(express.json());

const vapidKeys = {
  publicKey: "BO26_AnT1ZtuaJZtvyqlUSXGRTkPkP9eBI3CNUsBLoTyxb0Ew_TnRnWl6QgCbpL7XPcSnoy5Wo0HnS89Kd94KMU",
};

webpush.setVapidDetails(
  "mailto:dusty@dustore.ru",
  vapidKeys.publicKey,
  process.env.VAPID_PRIVATE
);

app.post("/send-push", (req, res) => {
  const subscription = JSON.parse(
    fs.readFileSync("subscriptions.json")
  );

  const payload = JSON.stringify({
    title: req.body.title || "Новое уведомление",
    body: req.body.body || "Есть событие",
    url: req.body.url || "/"
  });

  webpush.sendNotification(subscription, payload)
    .then(() => res.json({ ok: true }))
    .catch(err => {
      console.error(err);
      res.status(500).json({ ok: false });
    });
});

app.listen(3001, () => {
  console.log("Push server on :3001");
});