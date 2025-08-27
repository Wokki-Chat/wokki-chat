
let room;
let localAudioTrack = null;
let localVideoTrack = null;

const micBtn = document.getElementById("call-option-mic");
const videoBtn = document.getElementById("call-option-video");
let popupWindow = null;

async function joinLiveKitRoom(token, roomName) {
  const livekitWsUrl = "wss://chat.wokki20.nl/";

  try {
    room = new LivekitClient.Room({
      adaptiveStream: true,
      dynacast: true,
    });

    const micEnabled = sessionStorage.getItem("micEnabled") !== "false";
    const videoEnabled = sessionStorage.getItem("videoEnabled") === "true";

    await room.connect(livekitWsUrl, token, {
      name: roomName,
      audio: micEnabled,
      video: videoEnabled
    });

    localAudioTrack = await LivekitClient.createLocalAudioTrack();
    await room.localParticipant.publishTrack(localAudioTrack);
    localAudioTrack.mediaStreamTrack.enabled = micEnabled;

    if (videoEnabled) {
      localVideoTrack = await LivekitClient.createLocalVideoTrack();
      await room.localParticipant.publishTrack(localVideoTrack);
      localVideoTrack.mediaStreamTrack.enabled = true;
    }
    addAllParticipants();

    room.on(LivekitClient.RoomEvent.ParticipantConnected, participant => showParticipant(participant));
    room.on(LivekitClient.RoomEvent.ParticipantDisconnected, participant => removeParticipant(participant));
    room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, pub, participant) => handleTrackSubscribed(track, participant));
    room.on(LivekitClient.RoomEvent.TrackUnsubscribed, (track, pub, participant) => handleTrackUnsubscribed(track, participant));
    room.on(LivekitClient.RoomEvent.ActiveSpeakersChanged, updateActiveSpeakers);

    updateMicIcon(micEnabled);
    updateVideoIcon(videoEnabled);

    sessionStorage.setItem('livekit_token', token);
    sessionStorage.setItem('livekit_room', roomName);

  } catch (err) {
    console.error("LiveKit join error:", err);
  }
}


function showParticipant(participant, doc = document) {
  const participantsContainer = doc.querySelector(".participants");
  const user = usersList.find(u => u.id === String(participant.identity));
  const username = sanitize(user?.username ?? "Unknown");
  const profilePicture = user?.profile_picture ?? "";

  const participantId = `participant-${participant.sid}`;
  if (doc.getElementById(participantId)) return;

  const participantHtml = `
    <div class="participant" id="${participantId}">
      <div class="participant-media">
        <img src="${profilePicture}" class="participant-fallback" />
      </div>
      <p>${username}</p>
    </div>
  `;
  participantsContainer.insertAdjacentHTML("beforeend", participantHtml);

  if (participant.tracks) {
    for (const pub of participant.tracks.values()) {
      if (pub.track && pub.track.kind === LivekitClient.Track.Kind.Video) {
        handleTrackSubscribed(pub.track, participant, doc);
        break;
      }
    }
  }
}

function addAllParticipants() {
  showParticipant(room.localParticipant);
  for (const participant of room.remoteParticipants.values()) {
    showParticipant(participant);
  }
}

function removeParticipant(participant, doc = document) {
  const participantEl = doc.getElementById(`participant-${participant.sid}`);
  if (participantEl) participantEl.remove();
}

function handleTrackSubscribed(track, participant, doc = document) {
  if (track.kind === LivekitClient.Track.Kind.Audio) {
    const audioElement = new Audio();
    audioElement.srcObject = new MediaStream([track.mediaStreamTrack]);
    audioElement.autoplay = true;
    audioElement.play().catch(e => console.warn('Audio play error:', e));
  }
  if (track.kind === LivekitClient.Track.Kind.Video) {
    const videoElement = document.createElement("video");
    videoElement.autoplay = true;
    videoElement.playsInline = true;
    videoElement.muted = participant === room.localParticipant;
    videoElement.srcObject = new MediaStream([track.mediaStreamTrack]);

    const el = doc.getElementById(`participant-${participant.sid}`)?.querySelector('.participant-media');
    if (el) {
      el.innerHTML = '';
      el.appendChild(videoElement);
    }
  }
}

function handleTrackUnsubscribed(track, participant, doc = document) {
  if (track.kind === LivekitClient.Track.Kind.Video) {
    const el = doc.getElementById(`participant-${participant.sid}`)?.querySelector('.participant-media');
    if (el) el.innerHTML = '';
  }
}

function updateActiveSpeakers(speakers, doc = document) {
  doc.querySelectorAll(".participant.talking").forEach(el => el.classList.remove("talking"));
  speakers.forEach(p => {
    const el = doc.getElementById(`participant-${p.sid}`);
    if (el) el.classList.add("talking");
  });
}

if (micBtn) micBtn.addEventListener("click", toggleMic);
if (videoBtn) videoBtn.addEventListener("click", toggleVideo);

async function toggleMic() {
  if (!localAudioTrack) return;
  const enabled = !localAudioTrack.mediaStreamTrack.enabled;
  localAudioTrack.mediaStreamTrack.enabled = enabled;
  updateMicIcon(enabled);
  sessionStorage.setItem("micEnabled", enabled);
}

async function toggleVideo() {
  if (!room) return;

  const enabled = localVideoTrack?.mediaStreamTrack.enabled || false;

  if (enabled) {
    await room.localParticipant.unpublishTrack(localVideoTrack);
    localVideoTrack.stop();
    localVideoTrack = null;
    updateVideoIcon(false);
    sessionStorage.setItem("videoEnabled", false);
    const ownEl = document.getElementById(`participant-${room.localParticipant.sid}`);
    if (ownEl) ownEl.querySelector(".participant-media").innerHTML = "";
  } else {
    localVideoTrack = await LivekitClient.createLocalVideoTrack();
    await room.localParticipant.publishTrack(localVideoTrack);
    updateVideoIcon(true);
    sessionStorage.setItem("videoEnabled", true);
  }
}

function updateMicIcon(isMicOn) {
  const icon = micBtn.querySelector(".call-option-icon");
  if (icon) icon.textContent = isMicOn ? "mic" : "mic_off";
}

function updateVideoIcon(isVideoOn) {
  const icon = videoBtn.querySelector(".call-option-icon");
  if (icon) icon.textContent = isVideoOn ? "videocam" : "videocam_off";
}

const endCallBtn = document.querySelector(".call-hangup");
if (endCallBtn) endCallBtn.addEventListener("click", endCall);

function endCall() {
  if (room) {
    room.disconnect();
    room = null;
  }
  sessionStorage.removeItem('livekit_token');
  sessionStorage.removeItem('livekit_room');
  window.close();
}