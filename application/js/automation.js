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
          setTimeout(() => proceedWithNextStep(), 60000);
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

function pushLog(logMsg, logType) {
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
    msg = `<span class="text-${textClass}">`;
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

function proceedWithNextStep() {
  request.open("get", "/Automation/step");
  request.send();
}

(function () {
  proceedWithNextStep();
})();
