let loadedData = [];

function loadAnalyticsData() {
  let request = new XMLHttpRequest();
  request.open("GET", "{{ analytics.graphDataURL }}");
  request.setRequestHeader(
    "Data-Export-Access-Token",
    "{{ analytics.access_key }}"
  );
  request.addEventListener("load", onAnalyticsDataLoaded);
  request.send();
}

function onAnalyticsDataLoaded(e) {
  if (e.target.status == 200) {
    const parsedResponse = JSON.parse(e.target.responseText);
    for (const key in parsedResponse.data) {
      loadedData.push([key, parsedResponse.data[key]]);
    }
    loadGraph();
  }
}

function loadGraph() {
  google.charts.load("current", { packages: ["corechart"] });
  google.charts.setOnLoadCallback(drawChart_{{ graph_element_id|raw }});
}

function drawChart_{{ graph_element_id|raw }}() {
  const spinnerElementId = "{{ graph_element_id }}-spinner";
  document.getElementById(spinnerElementId).remove();

  // Create the data table.
  var data = new google.visualization.DataTable();
  data.addColumn("string", "{{ analytics.hAxisTitle|e('js') }}");
  data.addColumn("number", "Hits");
  data.addRows(loadedData);

  // Set chart options
  var options = {
    hAxis: {
      title: "{{ analytics.hAxisTitle|e('js') }}",
    },
    vAxis: {
      title: "Hits",
    },
    legend: { position: "none" },
  };

  // Instantiate and draw our chart, passing in some options.
  var chart = new google.visualization.LineChart(
    document.getElementById("{{ graph_element_id }}")
  );
  chart.draw(data, options);
}

(function () {
  loadAnalyticsData();
})();
