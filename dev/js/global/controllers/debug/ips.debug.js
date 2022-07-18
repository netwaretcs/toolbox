'use strict';
var _p = _p || {},
    ogLog = console.log,
    ogTable = console.table,
    ogAssert = console.assert,
    ogClear = console.clear,
    ogCount = console.count,
    ogError = console.error,
    ogGroup = console.group,
    ogGroupCollapsed = console.groupCollapsed,
    ogGroupEnd = console.groupEnd,
    ogInfo = console.info,
    ogTime = console.time,
    ogTimeEnd = console.timeEnd,
    ogTrace = console.trace,
    ogWarn = console.warn,
    que = null,
    queue = {},
    timers = {},
    cCount = 1,
    pIndex = 1,
    qIndex = 0,
    _addToQueue = (html,count,type) => {
      pIndex++;
      queue[pIndex] = {
        count: count,
        html: html,
        type:type
      };
      qIndex = 0;
    },
    _process = (html, count = 1, type = 'log') => {
      var el = $('#elProfileConsoleLog'),
          container = $('#elProfileConsoleLog_list'),
          last = container.find('li:last-child').find('div.dtProfilerGroup'),
          countEl = el.find('.dtprofilerCount');
      if(el.length === 0){
        _addToQueue(html,count,type);
        return;
      }
      if (last.length !== 0 && type !== 'groupEnd') {
        last.find('ul:first').append(html);
      } else {
        container.append(html);
      }
      var cc = Number(countEl.attr('data-count'));
      cc = Number(count) + cc;
      countEl.html(cc).attr('data-count', cc);
      el.addClass('dtprofilerFlash');
      container.trigger('contentChange', [container.parent()]);
    },
    isEmpty = (obj) => {
  return Object.keys(obj).length === 0;
},
    getStackTrace = (type = 'log', min = 5, linkify = true) => {
      if (type === 'timeEnd') {
        min = 6;
      }
      let file,
          other,
          line = 0,
          path,
          url,
          matches,
          main,
          error = new Error(),
          stack = error.stack || '';

      stack = stack.split('\n').map(function(line) {
        return line.trim();
      });
      stack = stack.splice(stack[0] === 'Error' ? min : 1);
      main = stack[0];
      //http://codingjungle.test/dev/applications/toolbox/dev/js/global/controllers/main/ips.toolbox.main.js?v=022c8961120a686efa330e667336b7cd1657607257:6:23)
      matches = main.match(/\bhttps?:\/\/\S+/gi);
      if (linkify && dtProfilerEditor) {
        url = matches[0].replace(')', '').split('/');
        file = url[url.length - 1];

        path = matches[0].replace(')', '').replace(dtProfilerBaseUrl, '');
        path = path.split('?');

        try {
          other = path[1].split(':');
          line = other[1] ?? 0;
        } catch (error) {
        }
        path = path[0];

        return '<div><a href="' + dtProfilerEditor + '://open?file=' +
            dtProfilerAppPath + '/' + path + '&line=' + line + '">in ' + path +
            ':' + line + ' via console.' + type + '()</a></div>';
      }

      return 'in ' + matches[0].replace(')', '') + ' via console.' + type +
          '()';
    };

que = setInterval(()=>{
  qIndex++;
  if(!isEmpty(queue)){
      $.each(queue, function(index,obj){
        if(obj.hasOwnProperty('count')){
          _process(obj.html,obj.count,obj.type);
          delete queue[index];
        }
      });
  }
  if(qIndex > 100 && Object.keys(queue).length === 0){
      clearInterval(que);
  }
},10);
_p = function() {
  var adapters,
      _buildTable = (table, headers = ['Index', 'Values']) => {
        let tables = '<table class="ipsTable">';
        if (headers) {
          tables += '<tr>';
          $.each(headers, function(index, name) {
            tables += '<th>' + name + '</th>';
          });
          tables += '<tr>';
        }
        $.each(table, function(index, value) {
          if (_.isObject(value) || _.isArray(value)) {
            tables += '<tr><td>' + index + '</td><td>' + value +
                _buildTable(value, ['Index', 'Values']) +
                '</td></tr>';

          } else {
            tables += '<tr><td>' + index + '</td><td>' + value +
                '</td></tr>';
          }
        });
        tables += '</table>';

        return tables;
      },
      _send = (data, type) => {
        let li = $('<li></li>'),
            container = $('<div></div>');
        li.addClass('ipsPad_half dtProfilerSearch dtProfilerType' + type);
        if (type !== 'groupEnd') {
          li.append(getStackTrace(type));
        } else {
          li.removeClass('ipsPad_half dtProfilerSearch');
        }
        li.append(data);
        container.html(li);
        _process(container.html(), 1, type);
      },
      newLog = (u, type = 'log', classes = null) => {
        let nv = '',
            includeIndex = u.length > 1;
        $.each(u, (index, value) => {
          if (_.isObject(value)) {
            value = _buildTable(value);
          }
          nv += '<div';
          if (!_.isNull(classes)) {
            nv += ' class="' + classes + '"';
          }
          nv += '>';
          if (includeIndex) {
            nv += index + ': ';
          }
          nv += value + '</div>';
        });
        _send(nv, type);
      },
      newTimeEnd = (label) => {
        let args = [],
            time = null;
        if (timers.hasOwnProperty(label)) {
          time = Date.now() - timers[label];
          args.push(label + ': ' + time + ' ms');
          newLog(args, 'timeEnd');
        } else {
          args.push('There are no timers for ' + label);
          newLog(args, 'timeEnd', 'warning');
        }
      },
      newGroupEnd = () => {
        _send(' ', 'groupEnd');
      },
      newGroup = (label, collapsed = false) => {
        let group = '<div class="dtProfilerGroup">';

        if (label) {
          group += '<h3>' + label + '</h3>';
        }
        if (collapsed) {
          group += '<i class="fa fa-chevron-circle-right" data-ipstoolboxcollapsed></i>';
        } else {
          group += '<i class="fa fa-chevron-circle-right fa-rotate-90" data-ipstoolboxcollapsed></i>';
        }
        group += '<ul class="ipsList_reset';
        if (collapsed) {
          group += ' closed ipsHide';
        }
        group += '"></ul></div>';
        _send(group, collapsed ? 'groupCollapsed' : 'group');
      },
      newTable = (obj, headers, type = 'table') => {
        let tables = _buildTable(obj, headers);
        _send(tables, type);
      },
      newCount = (label = 'default') => {
        let list = label + ': ' + cCount;
        _send(list, 'count');
        cCount++;
      },
      newClear = () => {
        let $this = $('#elProfileConsoleLog_list'),
            parent = $this.closest('ul.ipsList_reset');
        parent.find('li:not(.notme)').each(function() {
          $(this).remove();
        });
        parent.prev().find('.dtprofilerCount').html(0);
      },
      write = function(type, message, other = null, trace = false) {
        if (parseInt(dtProfilerDebug) === 1) {
          adapters.write(type, message, other, trace);
        }

        return _p;
      },
      l = function() {
        let args = Array.from(arguments);
        if (dtProfilerUseConsole) {
          newLog(args);
          return _p;
        } else {
          return write('log', args, null, true);
        }
      },
      t = function() {
        let args = Array.from(arguments),
            msg = args[0],
            headers = args[1] ?? ['Index', 'Values'];

        if (dtProfilerUseConsole) {
          if (_.isObject(msg) || _.isArray(msg)) {
            newTable(msg, headers);
          } else {
            newLog(args, 'table');
          }
          return _p;
        } else {
          return write('table', msg, headers, true);
        }
      },
      a = function(assertion, msg) {
        if (dtProfilerUseConsole) {
          if (assertion) {
            let args = [];
            args.push(msg);
            newLog(args, 'assert');
          }
          return _p;
        } else {
          return write('a', true, msg, assertion);
        }
      },
      c = function() {
        if (dtProfilerUseConsole) {
          newClear();
          return _p;
        } else {
          return write('c');
        }
      },
      cc = function(label) {
        if (dtProfilerUseConsole) {
          newCount(label);
          return _p;

        } else {
          return write('cc', label, null, true);
        }

      },
      e = function(msg) {
        if (dtProfilerUseConsole) {
          let args = [];
          args.push(msg);
          newLog(args, 'error');
          return _p;
        } else {
          return write('e', msg, null, true);
        }
      },
      g = function(label) {
        if (dtProfilerUseConsole) {
          let args = [];
          args.push(label);
          newGroup(args);
          return _p;
        } else {
          return write('g', label);
        }
      },
      gc = function(label) {
        if (dtProfilerUseConsole) {
          let args = [];
          args.push(label);
          newGroup(args, true);
          return _p;
        } else {
          return write('gc', label);
        }
      },
      ge = function() {
        if (dtProfilerUseConsole) {
          newGroupEnd();
          return _p;
        } else {
          return write('ge');
        }
      },
      i = function() {
        let args = Array.from(arguments);
        if (dtProfilerUseConsole) {
          newLog(args, 'info');
          return _p;
        } else {
          return write('i', args, null, true);
        }
      },
      time = function(label) {
        if (dtProfilerUseConsole) {
          if (_.isUndefined(label) || _.isNull(label) ||
              _.isEmpty(label)) {
            label = 'default;';
          }
          timers[label] = Date.now();
        } else {
          return write('time', label);
        }
      },
      timeEnd = function(label) {
        if (dtProfilerUseConsole) {
          if (_.isUndefined(label) || _.isNull(label) ||
              _.isEmpty(label)) {
            label = 'default;';
          }
          newTimeEnd(label);
        } else {
          return write('timeEnd', label, null, true);
        }
      },
      trace = function() {

        if (dtProfilerUseConsole) {
          let args = [],
              error = new Error();
          args.push(error.stack);
          newLog(args, 'trace');
        } else {
          return write('trace');
        }
      },
      w = function() {
        let args = Array.from(arguments);
        if (dtProfilerUseConsole) {
          newLog(args, 'warn');
          return _p;
        } else {
          return write('w', args, null, true);
        }
      },
      addAdapter = function(adapter) {
        adapters = adapter;
        return _p;
      };
  return {
    l: l,
    log: l,
    t: t,
    table: t,
    a: a,
    assert: a,
    c: c,
    clear: c,
    cc: cc,
    count: cc,
    e: e,
    error: e,
    g: g,
    group: g,
    gc: gc,
    groupCollapsed: gc,
    ge: ge,
    groupEnd: ge,
    i: i,
    info: i,
    time: time,
    timeEnd: timeEnd,
    trace: trace,
    warn: w,
    w: w,
    addAdapter: addAdapter,
  };
}();
var Console = function() {
};
Console.prototype.write = function(type, msg, other, trace) {
  if (window.console) {
    switch (type) {
      case 'l':
      case 'log':
        ogLog(...msg);
        break;
      case 't':
      case 'table':
        ogTable(msg, other);
        break;
      case 'a':
      case 'assert':
        ogAssert(other, msg);
        break;
      case 'c':
      case 'clear':
        ogClear();
        break;
      case 'cc':
      case 'count':
        ogCount();
        break;
      case 'e':
      case 'error':
        ogError(msg);
        break;
      case 'g':
      case 'group':
        ogGroup(msg);
        break;
      case 'gc':
      case 'groupCollapsed':
        ogGroupCollapsed(msg);
        break;
      case 'ge':
      case 'groupend':
        ogGroupEnd();
        break;
      case 'i':
      case 'info':
        ogInfo(msg);
        break;
      case 'time':
        ogTime(msg);
        break;
      case 'timeEnd':
        ogTimeEnd(msg);
        break;
      case 'trace':
        ogTrace();
        break;
      case 'w':
      case 'warn':
        ogWarn(msg);
        break;
    }
    if (trace === true) {
      ogLog(getStackTrace(type, 6, false));
    }
  }
};
_p.addAdapter(new Console);
if (dtProfilerReplaceConsole) {
  console.log = _p.l;
  console.table = _p.t;
  console.assert = _p.a;
  console.clear = _p.c;
  console.count = _p.cc;
  console.error = _p.e;
  console.group = _p.g;
  console.groupCollapsed = _p.gc;
  console.groupEnd = _p.ge;
  console.info = _p.i;
  console.time = _p.time;
  console.timeEnd = _p.timeEnd;
  console.trace = _p.trace;
  console.warn = _p.w;
}