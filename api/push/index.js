import express from "express";
import webpush from "web-push";
import mysql from "mysql2/promise";
import dotenv from "dotenv";

dotenv.config();

const app = express();
app.use(express.json());

const db = await mysql.createPool({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASS,
  database: process.env.DB_NAME
});

webpush.setVapidDetails(
  "mailto:admin@your.site",
  process.env.VAPID_PUBLIC,
  process.env.VAPID_PRIVATE
);


app.post("/send", async (req, res) => {
  const { user_id, title, body, url } = req.body;

  let sql = "SELECT * FROM push_subscriptions";
  let params = [];

  if (user_id) {
    sql += " WHERE user_id = ?";
    params.push(user_id);
  }

  const [subs] = await db.query(sql, params);

  const payload = JSON.stringify({ title, body, url });

  let sent = 0;

  for (const s of subs) {
    console.log("SEND PUSH TO:", s.endpoint);
    
    try {
      await webpush.sendNotification({
        endpoint: s.endpoint,
        keys: {
          p256dh: s.p256dh,
          auth: s.auth
        }
      }, payload);
      sent++;
    } catch (e) {
      if (e.statusCode === 410) {
        await db.query("DELETE FROM push_subscriptions WHERE endpoint=?", [s.endpoint]);
      }
    }
  }

  res.json({ ok: true, sent });
});

app.listen(3001, () => console.log("Push API :3001"));
