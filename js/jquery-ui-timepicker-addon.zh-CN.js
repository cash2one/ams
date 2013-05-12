jQuery(function($){
	$.datepicker.regional['zh-CN'] = {
		closeText: '确定',
		prevText: '<上月',
		nextText: '下月>',
		currentText: '现在',
		monthNames: ['一月','二月','三月','四月','五月','六月',
		'七月','八月','九月','十月','十一月','十二月'],
		monthNamesShort: ['一月','二月','三月','四月','五月','六月',
		'七月','八月','九月','十月','十一月','十二月'],
		dayNames: ['周日','周一','周二','周三','周四','周五','周六'],
		dayNamesShort: ['周日','周一','周二','周三','周四','周五','周六'],
		dayNamesMin: ['日','一','二','三','四','五','六'],
		weekHeader: '周',
		dateFormat: 'yy-mm-dd',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: true,
		yearSuffix: ''
	};
	$.datepicker.setDefaults($.datepicker.regional['zh-CN']);


	$.timepicker.regional['zh-CN'] = {
		timeOnlyTitle: '选择时间',
		timeText: '时间',
		hourText: '时',
		minuteText: '分',
		secondText: '秒',
		millisecText: '毫秒',
		timezoneText: '时区',
		currentText: '现在',
		closeText: '确定',
		timeFormat: 'HH:mm:ss',
		amNames: ['AM', 'A'],
		pmNames: ['PM', 'P'],
		isRTL: false
	};
	$.timepicker.setDefaults($.timepicker.regional['zh-CN']);
})
	