// Date extension ------------------------------------------------------------------------------------------------------

/** Constants used for time computations */
Date.SECOND = 1000 /* milliseconds */;
Date.MINUTE = 60 * Date.SECOND;
Date.HOUR   = 60 * Date.MINUTE;
Date.DAY    = 24 * Date.HOUR;
Date.WEEK   =  7 * Date.DAY;

function DateProxy(type, y, m, d, h, min, s) {
  if (type === 'Jalali') {
    var jdate = JalaliDate.jalaliToGregorian(y, m, d);
    //console.log('input for jdate: ' + y + ' ' + m + ' ' + d);
    //console.log('jdate: ' + jdate);
    return new Date(jdate[0], jdate[1] - 1, jdate[2], h || 0, min || 0, s || 0, 0); // jalali month is 1-based here
  } else {
    return new Date(y, m, d, h || 0, min || 0, s || 0, 0);
  }
}

Date.prototype.getJalaliFullYear = function() {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	return j[0];
};
Date.prototype.getUTCFullYearProxy = function (type) {
  if (type === 'Jalali') {
    return this.getJalaliFullYear();
  } else {
    return this.getUTCFullYear();
  }
};

Date.prototype.getJalaliMonth = function() {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	return j[1];
};
Date.prototype.getUTCMonthProxy = function (type) {
  if (type === 'Jalali') {
    return this.getJalaliMonth();
  } else {
    return this.getUTCMonth();
  }
};

Date.prototype.getJalaliDate = function() {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	return j[2];
};
Date.prototype.getUTCDateProxy = function (type) {
  if (type === 'Jalali') {
    return this.getJalaliDate();
  } else {
    return this.getDate();
  }
};

Date.prototype.getJalaliWeek = function() {
	var j = JalaliDate.jalaliToGregorian(this.getJalaliFullYear(), 1, 1);

	//First Saturday of the year
	var d = new Date(j[0], j[1]-1, j[2], 0, 0, 0);

	//Number of days after the first Saturday of the year
	var days = this.getJalaliDayOfYear() - ((7 - d.getJalaliDay()) % 7) - 1;

	if (days < 0) return new Date(this - this.getJalaliDay()*Date.DAY).getJalaliWeek();
	return Math.floor(days / 7) + 1;
};

Date.prototype.getJalaliDayOfYear = function() {
	var now = new Date(this.getFullYear(), this.getMonth(), this.getDate(), 0, 0, 0);
	var j = JalaliDate.jalaliToGregorian(this.getJalaliFullYear(), 1, 0);
	var then = new Date(j[0], j[1]-1, j[2], 0, 0, 0);
	var time = now - then;
	return Math.floor(time / Date.DAY);
};

Date.prototype.getJalaliDay = function() {
	var day = this.getDay();
	day = (day + 1) % 7;
	return day;
};

Date.prototype.setJalaliMonth = function(m, d) {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	if (m > 12) {
		j[0] += Math.floor(m / 12);
		m = m % 12;
	}
	j[1] = m;
	if (d != undefined) j[2] = d;
	var g = JalaliDate.jalaliToGregorian(j[0], j[1], j[2]);
	return this.setFullYear(g[0], g[1]-1, g[2]);
};
Date.prototype.setUTCMonthProxy = function (m, type) {
  if (type === 'Jalali') {
    this.setJalaliMonth(m);
  } else {
    this.setUTCMonth(m);
  }
};

Date.prototype.setJalaliFullYear = function(y, m, d) {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	if (y < 100) y += 1300;
	j[0] = y;
	if (m != undefined) {
		if (m > 11) {
			j[0] += Math.floor(m / 12);
			m = m % 12;
		}
		j[1] = m + 1;
	}
	if (d != undefined) j[2] = d;
	var g = JalaliDate.jalaliToGregorian(j[0], j[1], j[2]);
	return this.setFullYear(g[0], g[1]-1, g[2]);
};
Date.prototype.setUTCFullYearProxy = function (y, type) {
  if (type === 'Jalali') {
    this.setJalaliFullYear(y);
  } else {
    this.setUTCFullYear(y);
  }
};

Date.prototype.setJalaliDate = function(d) {
	var gd = this.getDate();
	var gm = this.getMonth();
	var gy = this.getFullYear();
	var j = JalaliDate.gregorianToJalali(gy, gm+1, gd);
	j[2] = d;
	var g = JalaliDate.jalaliToGregorian(j[0], j[1], j[2]);
	return this.setFullYear(g[0], g[1]-1, g[2]);
};
Date.prototype.setUTCDateProxy = function (d, type) {
  if (type === 'Jalali') {
    this.setJalaliDate(d);
  } else {
    this.setDate(d);
  }
};

// Date extension END --------------------------------------------------------------------------------------------------

// Jalali Date utils ---------------------------------------------------------------------------------------------------

var JalaliDate = {
	g_days_in_month: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
	j_days_in_month: [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]
};

JalaliDate.gregorianToJalali = function(g_y, g_m, g_d) {
	g_y = parseInt(g_y);
	g_m = parseInt(g_m);
	g_d = parseInt(g_d);
	var gy = g_y-1600;
	var gm = g_m-1;
	var gd = g_d-1;

	var g_day_no = 365*gy+parseInt((gy+3) / 4)-parseInt((gy+99)/100)+parseInt((gy+399)/400);

	for (var i=0; i < gm; ++i) //{
		g_day_no += JalaliDate.g_days_in_month[i];
		if (gm>1 && ((gy%4==0 && gy%100!=0) || (gy%400==0))) //{
			/* leap and after Feb */
			++g_day_no;
			g_day_no += gd;

			var j_day_no = g_day_no-79;

			var j_np = parseInt(j_day_no/ 12053);
			j_day_no %= 12053;

			var jy = 979+33*j_np+4*parseInt(j_day_no/1461);

			j_day_no %= 1461;

			if (j_day_no >= 366) {
				jy += parseInt((j_day_no-1)/ 365);
				j_day_no = (j_day_no-1)%365;
			}

			for (var i = 0; i < 11 && j_day_no >= JalaliDate.j_days_in_month[i]; ++i) {
				j_day_no -= JalaliDate.j_days_in_month[i];
			}
			var jm = i+1;
			var jd = j_day_no+1;


			return [jy, jm, jd];
		//}
	//}
};

JalaliDate.jalaliToGregorian = function(j_y, j_m, j_d) {

	j_y = parseInt(j_y);
	if (j_m > 12) j_y++;
	j_m = parseInt(j_m);
	j_d = parseInt(j_d);
	var jy = j_y-979;
	var jm = j_m-1;
	var jd = j_d-1;

	var j_day_no = 365*jy + parseInt(jy / 33)*8 + parseInt((jy%33+3) / 4);
	for (var i=0; i < jm; ++i) j_day_no += JalaliDate.j_days_in_month[i];

	j_day_no += jd;

	var g_day_no = j_day_no+79;

	var gy = 1600 + 400 * parseInt(g_day_no / 146097); /* 146097 = 365*400 + 400/4 - 400/100 + 400/400 */
	g_day_no = g_day_no % 146097;

	var leap = true;
	if (g_day_no >= 36525) /* 36525 = 365*100 + 100/4 */
	{
		g_day_no--;
		gy += 100*parseInt(g_day_no/  36524); /* 36524 = 365*100 + 100/4 - 100/100 */
		g_day_no = g_day_no % 36524;

		if (g_day_no >= 365)
			g_day_no++;
			else
				leap = false;
			}

			gy += 4*parseInt(g_day_no/ 1461); /* 1461 = 365*4 + 4/4 */
			g_day_no %= 1461;

			if (g_day_no >= 366) {
				leap = false;

				g_day_no--;
				gy += parseInt(g_day_no/ 365);
				g_day_no = g_day_no % 365;
			}

			for (var i = 0; g_day_no >= JalaliDate.g_days_in_month[i] + (i == 1 && leap); i++)
				g_day_no -= JalaliDate.g_days_in_month[i] + (i == 1 && leap);
				var gm = i+1;
				var gd = g_day_no+1;

				return [gy, gm, gd];
}
// Jalali Date utils  END ----------------------------------------------------------------------------------------------