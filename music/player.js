const audio = document.getElementById("audio");

const fileInput = document.getElementById("fileInput");
const fileButton = document.getElementById("fileButton");

const searchInput = document.getElementById("searchInput");

const library = document.getElementById("library");
const queue = document.getElementById("queue");

const libraryCount =
    document.getElementById("libraryCount");

const queueCount =
    document.getElementById("queueCount");

const clearQueueButton =
    document.getElementById("clearQueueButton");

const playButton =
    document.getElementById("playButton");

const prevButton =
    document.getElementById("prevButton");

const nextButton =
    document.getElementById("nextButton");

const shuffleButton =
    document.getElementById("shuffleButton");

const repeatButton =
    document.getElementById("repeatButton");

const volumeButton =
    document.getElementById("volumeButton");

const volumeSlider =
    document.getElementById("volumeSlider");

const currentTimeElement =
    document.getElementById("currentTime");

const durationElement =
    document.getElementById("duration");

const progressContainer =
    document.getElementById("progressContainer");

const progressFill =
    document.getElementById("progressFill");

const progressThumb =
    document.getElementById("progressThumb");

const dockCover =
    document.getElementById("dockCover");

const dockTitle =
    document.getElementById("dockTitle");

const dockSubtitle =
    document.getElementById("dockSubtitle");

const uploadOverlay =
    document.getElementById("uploadOverlay");

const uploadStatus =
    document.getElementById("uploadStatus");

const toast =
    document.getElementById("toast");

let tracks = [];
let playbackQueue = [];
let currentQueueIndex = -1;

let shuffle = false;
let repeat = false;

let isSeeking = false;
let lastVolume = 1;

let searchTimer = null;

function showToast(message) {
    toast.textContent = message;

    toast.classList.add("visible");

    clearTimeout(showToast.timer);

    showToast.timer = setTimeout(() => {
        toast.classList.remove("visible");
    }, 2500);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function formatTime(seconds) {
    if (!Number.isFinite(seconds)) {
        return "0:00";
    }

    const minutes =
        Math.floor(seconds / 60);

    const secondsPart =
        Math.floor(seconds % 60);

    return `${minutes}:${String(
        secondsPart
    ).padStart(2, "0")}`;
}

function pluralize(
    number,
    one,
    few,
    many
) {
    const n =
        Math.abs(number) % 100;

    const n1 = n % 10;

    if (n > 10 && n < 20) {
        return many;
    }

    if (n1 > 1 && n1 < 5) {
        return few;
    }

    if (n1 === 1) {
        return one;
    }

    return many;
}

async function api(
    url,
    options = {}
) {
    const response =
        await fetch(
            url,
            options
        );

    const data =
        await response.json();

    if (
        !response.ok ||
        data.success === false
    ) {
        throw new Error(
            data.error ||
            "Ошибка сервера"
        );
    }

    return data;
}

function trackTitle(track) {
    return (
        track.title ||
        track.original_name ||
        "Unknown"
    );
}

function trackArtist(track) {
    return (
        track.artist ||
        track.album_artist ||
        "Unknown artist"
    );
}

function trackSubtitle(track) {
    const values = [];

    if (track.artist) {
        values.push(track.artist);
    }

    if (track.album) {
        values.push(track.album);
    }

    return (
        values.join(" · ") ||
        "Music library"
    );
}

function coverHtml(
    track,
    className = ""
) {
    if (track.cover_url) {
        return `
            <img
                class="${className}"
                src="${escapeHtml(track.cover_url)}"
                alt=""
                loading="lazy"
            >
        `;
    }

    return "♪";
}

async function loadLibrary(
    query = ""
) {
    try {
        const data =
            await api(
                `index.php?action=search&q=${encodeURIComponent(query)}`
            );

        tracks =
            data.tracks || [];

        renderLibrary();

        libraryCount.textContent =
            `${tracks.length} ${pluralize(
                tracks.length,
                "трек",
                "трека",
                "треков"
            )}`;
    } catch (error) {
        console.error(error);

        library.innerHTML = `
            <div class="empty">
                Не удалось загрузить медиатеку
            </div>
        `;
    }
}

function renderLibrary() {
    if (!tracks.length) {
        library.innerHTML = `
            <div class="empty">
                Музыка пока не загружена
            </div>
        `;

        return;
    }

    library.innerHTML =
        tracks.map(track => {
            const isPlaying =
                playbackQueue[
                    currentQueueIndex
                ]?.id === track.id;

            return `
                <div
                    class="track ${isPlaying
                    ? "playing"
                    : ""
                }"
                    data-id="${track.id}"
                >

                    <div class="track-cover">
                        ${coverHtml(track)}
                    </div>

                    <div
                        class="track-info"
                        data-action="play"
                    >

                        <div class="track-name">
                            ${escapeHtml(
                    trackTitle(track)
                )}
                        </div>

                        <div class="track-artist">
                            ${escapeHtml(
                    trackArtist(track)
                )}
                        </div>

                        <div class="track-meta">
                            ${track.album
                    ? escapeHtml(
                        track.album
                    )
                    : ""
                }
                            ${track.duration
                    ? ` · ${formatTime(
                        track.duration
                    )}`
                    : ""
                }
                        </div>

                    </div>

                    <div class="track-actions">

                        <button
                            class="icon-button"
                            data-action="queue"
                            type="button"
                            title="Добавить в очередь"
                        >
                            <svg viewBox="0 0 24 24">
                                <path d="M12 5v14"/>
                                <path d="M5 12h14"/>
                            </svg>
                        </button>

                        <button
                            class="icon-button"
                            data-action="delete"
                            type="button"
                            title="Удалить"
                        >
                            <svg viewBox="0 0 24 24">
                                <path d="M4 7h16"/>
                                <path d="M10 11v6"/>
                                <path d="M14 11v6"/>
                                <path d="M6 7l1 13h10l1-13"/>
                                <path d="M9 7V4h6v3"/>
                            </svg>
                        </button>

                    </div>

                </div>
            `;
        }).join("");
}

function renderQueue() {
    queueCount.textContent =
        `${playbackQueue.length} ${pluralize(
            playbackQueue.length,
            "трек",
            "трека",
            "треков"
        )}`;

    if (!playbackQueue.length) {
        queue.innerHTML = `
            <div class="empty">
                Очередь пуста
            </div>
        `;

        return;
    }

    queue.innerHTML =
        playbackQueue.map(
            (track, index) => `
                <div
                    class="queue-item ${index === currentQueueIndex
                    ? "current"
                    : ""
                }"
                    data-index="${index}"
                >

                    <div class="queue-number">
                        ${index + 1}
                    </div>

                    <div class="queue-cover">
                        ${track.cover_url
                    ? `
                                <img
                                    src="${escapeHtml(
                        track.cover_url
                    )}"
                                    alt=""
                                >
                            `
                    : "♪"
                }
                    </div>

                    <div>

                        <div class="queue-name">
                            ${escapeHtml(
                    trackTitle(track)
                )}
                        </div>

                        <div class="queue-artist">
                            ${escapeHtml(
                    trackArtist(track)
                )}
                        </div>

                    </div>

                    <button
                        class="queue-remove"
                        data-action="remove"
                        type="button"
                    >
                        ×
                    </button>

                </div>
            `
        ).join("");
}

function saveQueue() {
    localStorage.setItem(
        "music_player_queue",
        JSON.stringify({
            queue: playbackQueue,
            index: currentQueueIndex
        })
    );
}

function restoreQueue() {
    try {
        const raw =
            localStorage.getItem(
                "music_player_queue"
            );

        if (!raw) {
            return;
        }

        const state =
            JSON.parse(raw);

        if (!Array.isArray(state.queue)) {
            return;
        }

        playbackQueue =
            state.queue;

        currentQueueIndex =
            Number.isInteger(
                state.index
            )
                ? state.index
                : -1;

        renderQueue();
        updateDock();
    } catch {
        localStorage.removeItem(
            "music_player_queue"
        );
    }
}

function addToQueue(
    track,
    autoplay = false
) {
    const existingIndex =
        playbackQueue.findIndex(
            item =>
                Number(item.id) ===
                Number(track.id)
        );

    if (existingIndex !== -1) {
        if (autoplay) {
            playQueueIndex(
                existingIndex
            );
        } else {
            showToast(
                "Трек уже находится в очереди"
            );
        }

        return;
    }

    playbackQueue.push(track);

    saveQueue();
    renderQueue();

    if (
        autoplay ||
        currentQueueIndex === -1
    ) {
        playQueueIndex(
            playbackQueue.length - 1
        );
    } else {
        showToast(
            "Добавлено в очередь"
        );
    }
}

function removeFromQueue(index) {
    if (
        index < 0 ||
        index >= playbackQueue.length
    ) {
        return;
    }

    const wasCurrent =
        index === currentQueueIndex;

    playbackQueue.splice(
        index,
        1
    );

    if (!playbackQueue.length) {
        currentQueueIndex = -1;

        audio.pause();

        audio.removeAttribute(
            "src"
        );

        audio.load();

        updateDock();

        saveQueue();
        renderQueue();
        renderLibrary();

        return;
    }

    if (
        index <
        currentQueueIndex
    ) {
        currentQueueIndex--;
    }

    if (wasCurrent) {
        if (
            currentQueueIndex >=
            playbackQueue.length
        ) {
            currentQueueIndex =
                playbackQueue.length - 1;
        }

        playQueueIndex(
            currentQueueIndex
        );
    }

    saveQueue();
    renderQueue();
    renderLibrary();
}

function clearQueue() {
    playbackQueue = [];
    currentQueueIndex = -1;

    audio.pause();

    audio.removeAttribute(
        "src"
    );

    audio.load();

    updateDock();

    saveQueue();
    renderQueue();
    renderLibrary();
}

function playQueueIndex(index) {
    if (
        index < 0 ||
        index >= playbackQueue.length
    ) {
        return;
    }

    const track =
        playbackQueue[index];

    currentQueueIndex =
        index;

    audio.src =
        track.file_url;

    audio.load();

    updateDock();
    renderQueue();
    renderLibrary();

    saveQueue();

    updateMediaSession(
        track
    );

    audio.play().catch(
        error => {
            console.error(error);

            showToast(
                "Не удалось начать воспроизведение"
            );
        }
    );
}

function playNext() {
    if (!playbackQueue.length) {
        return;
    }

    let nextIndex;

    if (
        shuffle &&
        playbackQueue.length > 1
    ) {
        do {
            nextIndex =
                Math.floor(
                    Math.random() *
                    playbackQueue.length
                );
        } while (
            nextIndex ===
            currentQueueIndex
        );
    } else {
        nextIndex =
            currentQueueIndex + 1;

        if (
            nextIndex >=
            playbackQueue.length
        ) {
            nextIndex = 0;
        }
    }

    playQueueIndex(
        nextIndex
    );
}

function playPrevious() {
    if (!playbackQueue.length) {
        return;
    }

    if (
        audio.currentTime > 3
    ) {
        audio.currentTime = 0;
        return;
    }

    let index =
        currentQueueIndex - 1;

    if (index < 0) {
        index =
            playbackQueue.length - 1;
    }

    playQueueIndex(index);
}

function togglePlay() {
    if (!audio.src) {
        if (playbackQueue.length) {
            playQueueIndex(
                currentQueueIndex >= 0
                    ? currentQueueIndex
                    : 0
            );
        } else {
            showToast(
                "Добавьте трек в очередь"
            );
        }

        return;
    }

    if (audio.paused) {
        audio.play().catch(
            () => { }
        );
    } else {
        audio.pause();
    }
}

function updatePlayingState() {
    document.body.classList.toggle(
        "player-playing",
        !audio.paused
    );
}

function updateDock() {
    if (
        currentQueueIndex < 0 ||
        !playbackQueue[
        currentQueueIndex
        ]
    ) {
        dockTitle.textContent =
            "Ничего не играет";

        dockSubtitle.textContent =
            "Выберите трек";

        dockCover.innerHTML =
            "♪";

        return;
    }

    const track =
        playbackQueue[
        currentQueueIndex
        ];

    dockTitle.textContent =
        trackTitle(track);

    dockSubtitle.textContent =
        trackSubtitle(track);

    dockCover.innerHTML =
        track.cover_url
            ? `
                <img
                    src="${escapeHtml(
                track.cover_url
            )}"
                    alt=""
                >
            `
            : "♪";
}

function updateProgress() {
    if (
        !Number.isFinite(
            audio.duration
        ) ||
        audio.duration <= 0
    ) {
        return;
    }

    const percentage =
        audio.currentTime /
        audio.duration *
        100;

    progressFill.style.width =
        `${percentage}%`;

    progressThumb.style.left =
        `${percentage}%`;

    currentTimeElement.textContent =
        formatTime(
            audio.currentTime
        );

    durationElement.textContent =
        formatTime(
            audio.duration
        );
}

function seek(event) {
    if (
        !Number.isFinite(
            audio.duration
        )
    ) {
        return;
    }

    const rect =
        progressContainer
            .getBoundingClientRect();

    let percentage =
        (
            event.clientX -
            rect.left
        ) /
        rect.width;

    percentage =
        Math.max(
            0,
            Math.min(
                1,
                percentage
            )
        );

    audio.currentTime =
        audio.duration *
        percentage;

    updateProgress();
}

function toggleShuffle() {
    shuffle = !shuffle;

    shuffleButton.classList.toggle(
        "active",
        shuffle
    );
}

function toggleRepeat() {
    repeat = !repeat;

    repeatButton.classList.toggle(
        "active",
        repeat
    );
}

function setVolume(value) {
    const volume =
        Math.max(
            0,
            Math.min(
                1,
                Number(value)
            )
        );

    audio.volume =
        volume;

    if (volume > 0) {
        lastVolume =
            volume;
    }
}

function toggleMute() {
    if (audio.volume > 0) {
        lastVolume =
            audio.volume;

        audio.volume = 0;
        volumeSlider.value = 0;
    } else {
        audio.volume =
            lastVolume || 1;

        volumeSlider.value =
            audio.volume;
    }
}

async function uploadFiles(
    files
) {
    if (!files.length) {
        return;
    }

    uploadOverlay.classList.add(
        "visible"
    );

    for (
        let i = 0;
        i < files.length;
        i++
    ) {
        const file =
            files[i];

        uploadStatus.textContent =
            `${i + 1} / ${files.length}: ${file.name}`;

        const formData =
            new FormData();

        formData.append(
            "file",
            file
        );

        try {
            const data =
                await api(
                    "index.php?action=upload",
                    {
                        method: "POST",
                        body: formData
                    }
                );

            const track =
                data.track;

            addToQueue(
                track,
                files.length === 1
            );
        } catch (error) {
            console.error(error);

            showToast(
                `${file.name}: ${error.message}`
            );
        }
    }

    uploadOverlay.classList.remove(
        "visible"
    );

    await loadLibrary(
        searchInput.value
    );

    renderQueue();
}

function updateMediaSession(
    track
) {
    if (
        !("mediaSession" in navigator)
    ) {
        return;
    }

    const artwork =
        track.cover_url
            ? [
                {
                    src:
                        track.cover_url,
                    type:
                        "image/jpeg",
                    sizes:
                        "512x512"
                }
            ]
            : [];

    navigator.mediaSession.metadata =
        new MediaMetadata({
            title:
                trackTitle(track),

            artist:
                track.artist ||
                "Unknown artist",

            album:
                track.album ||
                "Music library",

            artwork
        });

    navigator.mediaSession.playbackState =
        audio.paused
            ? "paused"
            : "playing";
}

function setupMediaSession() {
    if (
        !("mediaSession" in navigator)
    ) {
        return;
    }

    const handlers = {
        play: () => {
            audio.play().catch(
                () => { }
            );
        },

        pause: () => {
            audio.pause();
        },

        previoustrack: () => {
            playPrevious();
        },

        nexttrack: () => {
            playNext();
        },

        seekbackward: details => {
            const offset =
                details.seekOffset ||
                10;

            audio.currentTime =
                Math.max(
                    0,
                    audio.currentTime -
                    offset
                );
        },

        seekforward: details => {
            const offset =
                details.seekOffset ||
                10;

            audio.currentTime =
                Math.min(
                    audio.duration ||
                    Infinity,
                    audio.currentTime +
                    offset
                );
        },

        seekto: details => {
            if (
                details.fastSeek &&
                "fastSeek" in audio
            ) {
                audio.fastSeek(
                    details.seekTime
                );
            } else {
                audio.currentTime =
                    details.seekTime;
            }
        }
    };

    for (
        const [
            action,
            handler
        ] of Object.entries(
            handlers
        )
    ) {
        try {
            navigator.mediaSession
                .setActionHandler(
                    action,
                    handler
                );
        } catch {
        }
    }
}

library.addEventListener(
    "click",
    async event => {
        const element =
            event.target.closest(
                ".track"
            );

        if (!element) {
            return;
        }

        const id =
            Number(
                element.dataset.id
            );

        const track =
            tracks.find(
                item =>
                    Number(item.id) ===
                    id
            );

        if (!track) {
            return;
        }

        const actionElement =
            event.target.closest(
                "[data-action]"
            );

        const action =
            actionElement?.dataset.action;

        if (
            action === "queue"
        ) {
            addToQueue(
                track
            );

            return;
        }

        if (
            action === "delete"
        ) {
            const confirmed =
                confirm(
                    `Удалить "${trackTitle(
                        track
                    )}"?`
                );

            if (!confirmed) {
                return;
            }

            try {
                const formData =
                    new FormData();

                formData.append(
                    "id",
                    track.id
                );

                await api(
                    "index.php?action=delete",
                    {
                        method: "POST",
                        body: formData
                    }
                );

                playbackQueue =
                    playbackQueue.filter(
                        item =>
                            Number(item.id) !==
                            Number(track.id)
                    );

                if (
                    currentQueueIndex >=
                    playbackQueue.length
                ) {
                    currentQueueIndex =
                        playbackQueue.length -
                        1;
                }

                saveQueue();

                renderQueue();

                await loadLibrary(
                    searchInput.value
                );
            } catch (error) {
                showToast(
                    error.message
                );
            }

            return;
        }

        addToQueue(
            track,
            true
        );
    }
);

queue.addEventListener(
    "click",
    event => {
        const item =
            event.target.closest(
                ".queue-item"
            );

        if (!item) {
            return;
        }

        const index =
            Number(
                item.dataset.index
            );

        if (
            event.target.closest(
                "[data-action='remove']"
            )
        ) {
            removeFromQueue(
                index
            );

            return;
        }

        playQueueIndex(
            index
        );
    }
);

fileButton.addEventListener(
    "click",
    () => {
        fileInput.click();
    }
);

fileInput.addEventListener(
    "change",
    () => {
        const files =
            Array.from(
                fileInput.files || []
            );

        if (files.length) {
            uploadFiles(
                files
            );
        }

        fileInput.value = "";
    }
);

searchInput.addEventListener(
    "input",
    () => {
        clearTimeout(
            searchTimer
        );

        searchTimer =
            setTimeout(
                () => {
                    loadLibrary(
                        searchInput.value
                    );
                },
                250
            );
    }
);

clearQueueButton.addEventListener(
    "click",
    clearQueue
);

playButton.addEventListener(
    "click",
    togglePlay
);

prevButton.addEventListener(
    "click",
    playPrevious
);

nextButton.addEventListener(
    "click",
    playNext
);

shuffleButton.addEventListener(
    "click",
    toggleShuffle
);

repeatButton.addEventListener(
    "click",
    toggleRepeat
);

volumeButton.addEventListener(
    "click",
    toggleMute
);

volumeSlider.addEventListener(
    "input",
    () => {
        setVolume(
            volumeSlider.value
        );
    }
);

progressContainer.addEventListener(
    "pointerdown",
    event => {
        isSeeking = true;

        progressContainer
            .setPointerCapture?.(
                event.pointerId
            );

        seek(event);
    }
);

progressContainer.addEventListener(
    "pointermove",
    event => {
        if (isSeeking) {
            seek(event);
        }
    }
);

progressContainer.addEventListener(
    "pointerup",
    () => {
        isSeeking = false;
    }
);

progressContainer.addEventListener(
    "pointercancel",
    () => {
        isSeeking = false;
    }
);

audio.addEventListener(
    "loadedmetadata",
    updateProgress
);

audio.addEventListener(
    "timeupdate",
    updateProgress
);

audio.addEventListener(
    "play",
    () => {
        updatePlayingState();

        if (
            currentQueueIndex >= 0 &&
            playbackQueue[
            currentQueueIndex
            ]
        ) {
            updateMediaSession(
                playbackQueue[
                currentQueueIndex
                ]
            );
        }
    }
);

audio.addEventListener(
    "pause",
    () => {
        updatePlayingState();

        if (
            currentQueueIndex >= 0 &&
            playbackQueue[
            currentQueueIndex
            ]
        ) {
            updateMediaSession(
                playbackQueue[
                currentQueueIndex
                ]
            );
        }
    }
);

audio.addEventListener(
    "ended",
    () => {
        if (repeat) {
            audio.currentTime = 0;

            audio.play().catch(
                () => { }
            );

            return;
        }

        playNext();
    }
);

audio.addEventListener(
    "error",
    () => {
        showToast(
            "Не удалось воспроизвести файл"
        );
    }
);

document.addEventListener(
    "keydown",
    event => {
        if (
            event.target instanceof
            HTMLInputElement ||
            event.target instanceof
            HTMLTextAreaElement
        ) {
            return;
        }

        if (
            event.code === "Space"
        ) {
            event.preventDefault();
            togglePlay();
        }

        if (
            event.code ===
            "ArrowLeft"
        ) {
            audio.currentTime =
                Math.max(
                    0,
                    audio.currentTime - 5
                );
        }

        if (
            event.code ===
            "ArrowRight"
        ) {
            audio.currentTime =
                Math.min(
                    audio.duration ||
                    Infinity,
                    audio.currentTime + 5
                );
        }
    }
);

window.addEventListener(
    "beforeunload",
    saveQueue
);

setupMediaSession();
restoreQueue();
renderQueue();
loadLibrary();
setVolume(1);