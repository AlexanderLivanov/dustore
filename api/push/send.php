<script>
    app.post("/send", async (req, res) => {
        const {
            user_id,
            title,
            body,
            url
        } = req.body;

        let sql = "SELECT * FROM push_subscriptions";
        let params = [];

        if (user_id) {
            sql += " WHERE user_id = ?";
            params.push(user_id);
        }

        const [subs] = await db.query(sql, params);

        const payload = JSON.stringify({
            title,
            body,
            url
        });

        let sent = 0;

        for (const s of subs) {
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

        res.json({
            ok: true,
            sent
        });
    });

    app.listen(3001, () => console.log("Push API :3001"));
</script>