if (typeof globalThis.GanttList == 'undefined') {
  globalThis.GanttList = {};
}
for (const gantt_id in drupalSettings.gantt) {
  const settingsGantt = drupalSettings.gantt[gantt_id];
  let id_gantt = settingsGantt.id;
  GanttList[id_gantt] = new ClassGanttView(id_gantt, settingsGantt);
  GanttList[id_gantt].init(id_gantt, settingsGantt.data.data, settingsGantt.data.links);
}
