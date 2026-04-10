function clickGridButton(id_gantt, id, action) {
  if (!id && !action) {
    GanttList[id_gantt].Gantt.createTask();
  }
  switch (action) {
    case "edit":
      GanttList[id_gantt].Gantt.showLightbox(id);
      break;
    case "add":
      GanttList[id_gantt].Gantt.createTask(null, id);
      break;
    case "delete":
      GanttList[id_gantt].Gantt.confirm({
        title: GanttList[id_gantt].Gantt.locale.labels.confirm_deleting_title,
        text: GanttList[id_gantt].Gantt.locale.labels.confirm_deleting,
        callback: function (res) {
          if (res)
            GanttList[id_gantt].Gantt.deleteTask(id);
        }
      });
      break;
  }
}

function controlColumns(id_gantt, node) {
  let allColumns = GanttList[id_gantt].Gantt.config.columns;

  const getDropdownNode = () => {
    let elGantt = GanttList[id_gantt].Gantt.$root.closest('.gantt-wrapper');
    return elGantt.querySelector('#gantt_dropdown')
  }

  const hideDropDown = () => {
    let dropDown = getDropdownNode();
    dropDown.style.display = "none";
  }

  window.addEventListener("click", function(event){
    if(!event.target.closest("#gantt_dropdown") && !getDropdownNode().keep){
      hideDropDown();
    }
  });

  const populateColumnsDropdown = (node) => {
    let lines = [];
    allColumns.forEach(function(col){
      if (col.name !== 'control_columns') {
        let checked = col?.hide === true ? "" : "checked";
        lines.push("<label><input type='checkbox' name='"+col.name+"' "+checked+">" + (col.name === 'add' || col.name === 'buttons' ? '<span class="gantt_add"></span>' : col.label) + "</label>");
      }
    });
    node.innerHTML = lines.join("");
  }

  let parentElement = GanttList[id_gantt].Gantt.$root.closest('.gantt-wrapper');
  let positionParent = parentElement.getBoundingClientRect();
  let position = node.getBoundingClientRect();
  let dropDown = getDropdownNode();
  let top = position.top - positionParent.top + node.offsetHeight
  let left = position.left - positionParent.left
  dropDown.style.top = top + "px";
  dropDown.style.left = left + "px";
  dropDown.style.display = "block";
  populateColumnsDropdown(dropDown);

  dropDown.onchange = function() {
    let hideStorage = {};
    let selectedColumns = dropDown.querySelectorAll("input[type='checkbox']");
    selectedColumns.forEach(function(node) {
      let col = GanttList[id_gantt].Gantt.config.columns.find(item => item.name === node.name)
      if (col) {
        col.hide = !node.checked;
        if (!node.checked) hideStorage[col.name] = !node.checked;
      }
    });

    window.localStorage.setItem('hide_column_' + id_gantt, JSON.stringify(hideStorage));

    GanttList[id_gantt].Gantt.render();
  }

  dropDown.keep = true;
  setTimeout(function() {
    dropDown.keep = false;
  })
}
