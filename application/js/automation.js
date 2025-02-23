const request = new XMLHttpRequest();
request.addEventListener("loadend", onRequestEnds);

function onRequestEnds(e) {
  try {
    const parsedResponse = JSON.parse(e.target.responseText);
    for (const log of parsedResponse.logs) {
      pushLog(log[0], log[1]);
    }

    const progressBar = document.getElementById("automation-progress");
    if (parsedResponse.total > 0) {
      let progress = (parsedResponse.current / parsedResponse.total) * 100;
      progressBar.style.setProperty("width", `${progress}%`);
    } else {
      progressBar.style.setProperty("width", "0%");
    }

    switch (parsedResponse.status) {
      case "ok":
        proceedWithNextStep();
        break;
      case "cp":
        pushLog("Process complete.", "l");
        break;
      case "fl":
        if (parsedResponse.httpStatusCode == 429) {
          pushLog("Too Many Requests. Waiting for 60s and continuing...", "w");
          startCountdown(60, () => proceedWithNextStep());
        }
        else {
          pushLog("Process failed.", "e");
        } 
        break;
      default:
        pushLog(`Unknown status '${parsedResponse.status}'`, "w");
        break;
    }
  } catch (e) {
    console.log(e);
    pushLog(
      `An error has occured while parsing request response:\n${e.target.responseText}\nError:\n${e}`,
      "e"
    );
  }
}

function pushLog(logMsg, logType, extraClass = '') {
  let textClass = undefined;
  switch (logType) {
    case "d":
      textClass = "info";
      break;
    case "w":
      textClass = "warning";
      break;
    case "e":
      textClass = "danger";
      break;
  }

  let msg = "";
  if (textClass) {
    msg = `<span class="text-${textClass} ${extraClass}">`;
  }
  msg += logMsg;
  if (textClass) {
    msg += "</span>";
  }
  msg += "\n";

  const logContainer = document.getElementById("automation-log");
  logContainer.innerHTML += msg;
  logContainer.scrollTo(0, logContainer.scrollHeight);
}

function startCountdown(seconds, callback) {
  let remainingSeconds = seconds;
  updateCountdownLabel(remainingSeconds);
  let intervalRef = setInterval(function() {
    remainingSeconds--;
    if (remainingSeconds <= 0) {
      clearInterval(intervalRef);
      clearCountdownLabel();
      proceedWithNextStep();
    }
    else {
      updateCountdownLabel(remainingSeconds);
    }
  }, 1000);
}

function updateCountdownLabel(remainingSeconds) {
  const logContainer = document.getElementById('automation-log');
  const logCountdownElements = logContainer.getElementsByClassName('log-countdown');
  const msg = `${remainingSeconds}`;
  if (logCountdownElements.length > 0) {
    logCountdownElements[0].innerHTML = msg;
  }
  else {
    pushLog(msg, 'w', 'log-countdown');
  }
}

function clearCountdownLabel() {
  const logContainer = document.getElementById('automation-log');
  const logCountdownElements = logContainer.getElementsByClassName('log-countdown');
  if (logCountdownElements.length > 0) {
    logCountdownElements[0].remove();
    logContainer.innerHTML = logContainer.innerHTML.substring(0, logContainer.innerHTML.lastIndexOf("\n"));
  }
}

function proceedWithNextStep() {
  request.open("get", "/Automation/step");
  request.send();
}

(function () {
  proceedWithNextStep();
})();
