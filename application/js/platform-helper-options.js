function changeForm(form, option, value) {
  var optionInput = form.elements["option"];
  optionInput.value = option;
  var valueInput = form.elements["value"];
  valueInput.value = value;
  form.submit();
}
