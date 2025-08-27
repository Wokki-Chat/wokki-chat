let room;

async function joinLiveKitRoom(token, roomName) {
  const livekitWsUrl = "wss://chat.wokki20.nl/";

  try {
    room = new LivekitClient.Room({
      adaptiveStream: true,
      dynacast: true,
    });

    await room.connect(livekitWsUrl, token, { 
      name: roomName,
      audio: true, 
      video: false 
    });

    const audioTrack = await LivekitClient.createLocalAudioTrack();
    await room.localParticipant.publishTrack(audioTrack);

    room.on(LivekitClient.RoomEvent.ParticipantConnected, participant => {

    });

    room.on(LivekitClient.RoomEvent.TrackSubscribed, (track, publication, participant) => {
      if (track.kind === LivekitClient.Track.Kind.Audio) {
        const audioElement = new Audio();
        audioElement.srcObject = new MediaStream([track.mediaStreamTrack]);
        audioElement.autoplay = true;
        audioElement.play().catch(e => console.warn('Audio play error:', e));
      }
    });

    room.on(LivekitClient.RoomEvent.ParticipantDisconnected, participant => {

    });

    sessionStorage.setItem('livekit_token', token);
    sessionStorage.setItem('livekit_room', roomName);

  } catch (err) {
    console.error("LiveKit join error:", err);
  }
}

window.addEventListener('load', async () => {
  const token = sessionStorage.getItem('livekit_token');
  const roomName = sessionStorage.getItem('livekit_room');

  if (token && roomName) {
    await joinLiveKitRoom(token, roomName);
  }
});
