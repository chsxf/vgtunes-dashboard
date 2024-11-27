const apiURL = "/Suggestions/album/{ALBUM_NAME}";
const spotifyURL = "/Spotify/query?title={TITLE}&artist={ARTIST}";
const appleMusicURL = "/AppleMusic/query?title={TITLE}&artist={ARTIST}";

let suggestionsTimeout = null;
let suggestionsRequest = new XMLHttpRequest();
suggestionsRequest.addEventListener("load", onSuggestionsLoaded);

let spotifyRequest = new XMLHttpRequest();
spotifyRequest.addEventListener("load", onSpotifyAnswerReceived);

let appleMusicRequest = new XMLHttpRequest();
appleMusicRequest.addEventListener("load", onAppleMusicAnswerReceived);

function setupSuggestionTimeout() {
  if (suggestionsTimeout !== null) {
    clearTimeout(suggestionsTimeout);
    suggestionsRequest.abort();
  }
  suggestionsTimeout = setTimeout(requestSuggestions, 1000);
}

function requestSuggestions() {
  let albumName = `"${$("#name").val()}"`;
  let url = apiURL.replace("{ALBUM_NAME}", encodeURI(albumName));
  suggestionsRequest.open("GET", url);
  suggestionsRequest.send();
}

function onSuggestionsLoaded() {
  clearSuggestions();

  let responseJson;
  if (suggestionsRequest.responseType == "json") {
    responseJson = suggestionsRequest.response;
  } else {
    responseJson = JSON.parse(suggestionsRequest.responseText);
  }

  if (responseJson.hasOwnProperty("entries")) {
    let containingList = $("#suggestions ul");
    for (let entry of responseJson.entries) {
      let platformIds = [`data-deezer-id="${entry.instances.deezer}"`];
      containingList.append(
        `<li ${platformIds.join(" ")} data-title="${
          entry.title
        }" data-cover-url="${entry.cover}" data-artist="${entry.artist}">${
          entry.title
        } - ${entry.artist}</li>`
      );
      containingList.find("li:last").on("click", onSuggestionClicked);
    }
    containingList.parent().show();
  }
}

function onSuggestionClicked() {
  const title = $(this).data("title");
  const artist = $(this).data("artist");

  $("#name").val(title);

  const deezerId = $(this).data("deezer-id");
  $("#deezer_platform_id").val(deezerId);
  updateDeezerUrl(deezerId);
  clearSuggestions();

  $("#artist_name").val(artist);

  $("#cover img").attr("src", $(this).data("cover-url"));
  $("#cover").show();

  lookForMatchOnSpotify(title, artist);
  lookForMatchOnAppleMusic(title, artist);
}

function lookForMatchOnSpotify(title, artist) {
  let url = spotifyURL.replace("{TITLE}", title).replace("{ARTIST}", artist);
  spotifyRequest.open("GET", url);
  spotifyRequest.send();

  $("#spotify_log").text("Querying Spotify...");
}

function onSpotifyAnswerReceived() {
  let responseJson;
  if (spotifyRequest.responseType == "json") {
    responseJson = spotifyRequest.response;
  } else {
    responseJson = JSON.parse(spotifyRequest.responseText);
  }

  if (responseJson.exactMatch) {
    $("#spotify_platform_id").val(responseJson.candidate.id);
    $("#spotify_log").text(
      `Exact match found! [${responseJson.candidate.title} - ${responseJson.candidate.artist}`
    );
  } else {
    $("#spotify_log").text(
      `${repsonseJson.candidates.length} candidates found`
    );
  }
}

function lookForMatchOnAppleMusic(title, artist) {
  let url = appleMusicURL.replace("{TITLE}", title).replace("{ARTIST}", artist);
  appleMusicRequest.open("GET", url);
  appleMusicRequest.send();

  $("#apple_music_log").text("Querying Apple Music...");
}

function onAppleMusicAnswerReceived() {
  let responseJson;
  if (spotifyRequest.responseType == "json") {
    responseJson = appleMusicRequest.response;
  } else {
    responseJson = JSON.parse(appleMusicRequest.responseText);
  }

  if (responseJson.exactMatch) {
    $("#apple_music_platform_id").val(responseJson.candidate.id);
    $("#apple_music_log").text(
      `Exact match found! [${responseJson.candidate.title} - ${responseJson.candidate.artist}`
    );
  } else {
    $("#apple_music_log").text(
      `${repsonseJson.candidates.length} candidates found`
    );
  }
}

function updateDeezerUrl(id) {
  if (id == "") {
    $("#deezer_url").hide();
  } else {
    $("#deezer_url").text(`https://www.deezer.com/fr/album/${id}`);
  }
}

function clearSuggestions() {
  $("#suggestions ul").empty();
  $("#suggestions").hide();
}

function setupCoverInteractions() {
  $("#cover img").on("click", function () {
    window.open($(this).attr("src"), "_blank");
  });
  $("#cover a").on("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    let imgUrl = $("#cover img").attr("src");
    let processingUrl = `/ImageProcessing/covers?url=${encodeURI(imgUrl)}`;
    window.open(processingUrl, "_blank");
  });
}

function setupPrefill() {
  $("#name").on("input", function () {
    setupSuggestionTimeout();
  });

  $("#deezer_platform_id").on("input", function () {
    updateDeezerUrl($(this).val());
  });

  $("#deezer_url").on("click", function () {
    if (navigator.clipboard) {
      navigator.clipboard.writeText($(this).text());
    }
  });

  clearSuggestions();

  $("#cover").hide();
  setupCoverInteractions();
}

$(function () {
  setupPrefill();
});
