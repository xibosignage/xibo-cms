const PlayerHelper = function() {
  // Check the query params to see if we're in editor mode
  const _self = this;
  const urlParams = new URLSearchParams(window.location.search);
  const isPreview = urlParams.get('preview') === '1';

  this.init = (widgetData, elements) => new Promise((resolve) => {
    if (Array.isArray(widgetData)) {
      const _widgetData = [...widgetData];
      const _widgetPromises = [];

      _widgetData.forEach((widget) => {
        _widgetPromises.push(this.getWidgetData(widget));
      });

      Promise.all(_widgetPromises).then((values) => {
        const widgets = {};
        values.forEach((value, widgetIndex) => {
          let _elements = [];
          const _widget = _widgetData[widgetIndex];
          const {dataItems, showError} = this.composeFinalData(_widget, value);

          if (elements !== undefined && elements?.length > 0) {
            elements.forEach(function(elemVal) {
              if (elemVal?.length > 0) {
                elemVal.forEach(function(elemObj) {
                  if (elemObj.widgetId === _widget.widgetId) {
                    _elements = _self.composeElements(elemObj?.elements || []);
                  }
                });
              }
            });
          }

          widgets[_widget.widgetId] = {
            ..._widgetData[widgetIndex],
            data: dataItems,
            meta: value !== null ? value?.meta : {},
            showError,
            elements: _elements,
          };
        });

        resolve({widgets});
      });
    }
  });

  /**
   * onDataError callback
   * @param {Object} widget - Widget
   * @param {string|number} httpStatus
   * @param {Object} response - Response body|json
   */
  this.onDataErrorCallback = (widget, httpStatus, response) => {
    const onDataError = window[
      `onDataError_${widget.widgetId}`
    ];

    if (typeof onDataError === 'function') {
      if (onDataError(httpStatus, response) == false) {
        xiboIC.reportFault({
          code: '5001',
          reason: 'No Data',
        }, {targetId: widget.widgetId});
      }

      onDataError(httpStatus, response);
    } else {
      xiboIC.reportFault({
        code: '5001',
        reason: 'No Data',
      }, {targetId: widget.widgetId});
    }
  };

  /**
   * Get widget data
   * @param {object} widget
   * @return {Promise}
   */
  this.getWidgetData = (widget) => {
    return new Promise(function(resolve) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      // if (!isPreview) {
      if (widget.data?.data !== undefined) {
        resolve(widget.data);
      } else if (widget.url) {
        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax({
          method: 'GET',
          url: widget.url,
        }).done(function(data) {
          if (data && data.hasOwnProperty('success') &&
              data.success === false && data.error
          ) {
            _self.onDataErrorCallback(widget, data.error, data);
          }

          if (Array.isArray(data) && data.length === 0) {
            xiboIC.expireNow({targetId: xiboICTargetId});
          }

          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          _self.onDataErrorCallback(widget, jqXHR.status, jqXHR.responseJSON);
          console.log(jqXHR, textStatus, errorThrown);

          if (jqXHR.status === 404) {
            xiboIC.expireNow({targetId: xiboICTargetId});
          }
        });
      } else {
        resolve(null);
      }
    });
  };

  /**
   * Compose final data
   * @param {object} widget
   * @param {object|array} data
   * @return {object}
   */
  this.composeFinalData = (widget, data) => {
    const finalData = {
      isSampleData: false,
      dataItems: [],
      isArray: Array.isArray(data?.data),
      showError: false,
    };
    const composeSampleData = () => {
      finalData.isSampleData = true;

      if (widget.sample === null) {
        finalData.dataItems = [];
        return [];
      }

      // If data is empty, use sample data instead
      // Add single element or array of elements
      finalData.dataItems = (Array.isArray(widget.sample)) ?
        widget.sample.slice(0) : [widget.sample];

      return finalData.dataItems.reduce((data, item) => {
        Object.keys(item).forEach((itemKey) => {
          if (String(item[itemKey])
            .match(DateFormatHelper.macroRegex) !== null) {
            item[itemKey] = DateFormatHelper
              .composeUTCDateFromMacro(item[itemKey]);
          }
        });

        return [...data, {...item}];
      }, []);
    };

    if (isPreview) {
      if (finalData.isArray) {
        if (data?.data?.length > 0) {
          finalData.dataItems = data?.data;
        } else if (widget.sample && Array.isArray(widget.sample)) {
          finalData.dataItems = composeSampleData();
        }
      } else if (data?.success === false || !widget.isValid) {
        finalData.dataItems = composeSampleData();
      }
    } else {
      finalData.dataItems = data?.data || [];
    }

    if (data?.success === false || !widget.isValid) {
      finalData.showError = true;
    }

    return finalData;
  };

  /**
   * Compose widget elements
   * @param {array} elements
   * @return {array} elements
   */
  this.composeElements = (elements) => {
    return elements;
  };

  this.getMaxSlot = (objectsArray, itemsKey, minValue) => {
    const groupItems = objectsArray?.length > 0 ?
      objectsArray.reduce(
        (a, b) => [...a, ...b[itemsKey]], []) : null;

    return groupItems === null ?
      minValue :
      Math.max(...groupItems.map(function(elem) {
        return elem?.slot || 0;
      })) + 1;
  };

  this.setPlayerElements = function(elements, widget) {};

  this.isGroup = function(element) {
    return element.hasOwnProperty('groupId');
  };

  this.elementGroups = function(elements) {
    // const standalone = {}; const groups = {};
  };

  this.getStandaloneSlotsData = function(elements, data) {};

  this.getGroupSlotsData = function(elements, data) {};

  return this;
};

module.exports = new PlayerHelper();
