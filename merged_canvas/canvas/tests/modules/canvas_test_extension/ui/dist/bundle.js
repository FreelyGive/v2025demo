
(function(l, r) { if (!l || l.getElementById('livereloadscript')) return; r = l.createElement('script'); r.async = 1; r.src = '//' + (self.location.host || 'localhost').split(':')[0] + ':35729/livereload.js?snipver=1'; r.id = 'livereloadscript'; l.getElementsByTagName('head')[0].appendChild(r) })(self.document);
(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? factory(require('react-dom'), require('react')) :
  typeof define === 'function' && define.amd ? define(['react-dom', 'react'], factory) :
  (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.ReactDom, global.React));
})(this, (function (require$$0, ReactOriginal) { 'use strict';

  function _interopDefaultLegacy (e) { return e && typeof e === 'object' && 'default' in e ? e : { 'default': e }; }

  function _interopNamespace(e) {
    if (e && e.__esModule) return e;
    var n = Object.create(null);
    if (e) {
      Object.keys(e).forEach(function (k) {
        if (k !== 'default') {
          var d = Object.getOwnPropertyDescriptor(e, k);
          Object.defineProperty(n, k, d.get ? d : {
            enumerable: true,
            get: function () { return e[k]; }
          });
        }
      });
    }
    n["default"] = e;
    return Object.freeze(n);
  }

  var require$$0__default = /*#__PURE__*/_interopDefaultLegacy(require$$0);
  var ReactOriginal__default = /*#__PURE__*/_interopDefaultLegacy(ReactOriginal);
  var ReactOriginal__namespace = /*#__PURE__*/_interopNamespace(ReactOriginal);

  var withSelector = {exports: {}};

  var useSyncExternalStoreWithSelector_development = {};

  /**
   * @license React
   * use-sync-external-store-with-selector.development.js
   *
   * Copyright (c) Facebook, Inc. and its affiliates.
   *
   * This source code is licensed under the MIT license found in the
   * LICENSE file in the root directory of this source tree.
   */

  var hasRequiredUseSyncExternalStoreWithSelector_development;

  function requireUseSyncExternalStoreWithSelector_development () {
  	if (hasRequiredUseSyncExternalStoreWithSelector_development) return useSyncExternalStoreWithSelector_development;
  	hasRequiredUseSyncExternalStoreWithSelector_development = 1;

  	{
  	  (function() {

  	/* global __REACT_DEVTOOLS_GLOBAL_HOOK__ */
  	if (
  	  typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ !== 'undefined' &&
  	  typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart ===
  	    'function'
  	) {
  	  __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStart(new Error());
  	}
  	          var React = ReactOriginal__default["default"];

  	/**
  	 * inlined Object.is polyfill to avoid requiring consumers ship their own
  	 * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/is
  	 */
  	function is(x, y) {
  	  return x === y && (x !== 0 || 1 / x === 1 / y) || x !== x && y !== y // eslint-disable-line no-self-compare
  	  ;
  	}

  	var objectIs = typeof Object.is === 'function' ? Object.is : is;

  	var useSyncExternalStore = React.useSyncExternalStore;

  	// for CommonJS interop.

  	var useRef = React.useRef,
  	    useEffect = React.useEffect,
  	    useMemo = React.useMemo,
  	    useDebugValue = React.useDebugValue; // Same as useSyncExternalStore, but supports selector and isEqual arguments.

  	function useSyncExternalStoreWithSelector(subscribe, getSnapshot, getServerSnapshot, selector, isEqual) {
  	  // Use this to track the rendered snapshot.
  	  var instRef = useRef(null);
  	  var inst;

  	  if (instRef.current === null) {
  	    inst = {
  	      hasValue: false,
  	      value: null
  	    };
  	    instRef.current = inst;
  	  } else {
  	    inst = instRef.current;
  	  }

  	  var _useMemo = useMemo(function () {
  	    // Track the memoized state using closure variables that are local to this
  	    // memoized instance of a getSnapshot function. Intentionally not using a
  	    // useRef hook, because that state would be shared across all concurrent
  	    // copies of the hook/component.
  	    var hasMemo = false;
  	    var memoizedSnapshot;
  	    var memoizedSelection;

  	    var memoizedSelector = function (nextSnapshot) {
  	      if (!hasMemo) {
  	        // The first time the hook is called, there is no memoized result.
  	        hasMemo = true;
  	        memoizedSnapshot = nextSnapshot;

  	        var _nextSelection = selector(nextSnapshot);

  	        if (isEqual !== undefined) {
  	          // Even if the selector has changed, the currently rendered selection
  	          // may be equal to the new selection. We should attempt to reuse the
  	          // current value if possible, to preserve downstream memoizations.
  	          if (inst.hasValue) {
  	            var currentSelection = inst.value;

  	            if (isEqual(currentSelection, _nextSelection)) {
  	              memoizedSelection = currentSelection;
  	              return currentSelection;
  	            }
  	          }
  	        }

  	        memoizedSelection = _nextSelection;
  	        return _nextSelection;
  	      } // We may be able to reuse the previous invocation's result.


  	      // We may be able to reuse the previous invocation's result.
  	      var prevSnapshot = memoizedSnapshot;
  	      var prevSelection = memoizedSelection;

  	      if (objectIs(prevSnapshot, nextSnapshot)) {
  	        // The snapshot is the same as last time. Reuse the previous selection.
  	        return prevSelection;
  	      } // The snapshot has changed, so we need to compute a new selection.


  	      // The snapshot has changed, so we need to compute a new selection.
  	      var nextSelection = selector(nextSnapshot); // If a custom isEqual function is provided, use that to check if the data
  	      // has changed. If it hasn't, return the previous selection. That signals
  	      // to React that the selections are conceptually equal, and we can bail
  	      // out of rendering.

  	      // If a custom isEqual function is provided, use that to check if the data
  	      // has changed. If it hasn't, return the previous selection. That signals
  	      // to React that the selections are conceptually equal, and we can bail
  	      // out of rendering.
  	      if (isEqual !== undefined && isEqual(prevSelection, nextSelection)) {
  	        return prevSelection;
  	      }

  	      memoizedSnapshot = nextSnapshot;
  	      memoizedSelection = nextSelection;
  	      return nextSelection;
  	    }; // Assigning this to a constant so that Flow knows it can't change.


  	    // Assigning this to a constant so that Flow knows it can't change.
  	    var maybeGetServerSnapshot = getServerSnapshot === undefined ? null : getServerSnapshot;

  	    var getSnapshotWithSelector = function () {
  	      return memoizedSelector(getSnapshot());
  	    };

  	    var getServerSnapshotWithSelector = maybeGetServerSnapshot === null ? undefined : function () {
  	      return memoizedSelector(maybeGetServerSnapshot());
  	    };
  	    return [getSnapshotWithSelector, getServerSnapshotWithSelector];
  	  }, [getSnapshot, getServerSnapshot, selector, isEqual]),
  	      getSelection = _useMemo[0],
  	      getServerSelection = _useMemo[1];

  	  var value = useSyncExternalStore(subscribe, getSelection, getServerSelection);
  	  useEffect(function () {
  	    inst.hasValue = true;
  	    inst.value = value;
  	  }, [value]);
  	  useDebugValue(value);
  	  return value;
  	}

  	useSyncExternalStoreWithSelector_development.useSyncExternalStoreWithSelector = useSyncExternalStoreWithSelector;
  	          /* global __REACT_DEVTOOLS_GLOBAL_HOOK__ */
  	if (
  	  typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ !== 'undefined' &&
  	  typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop ===
  	    'function'
  	) {
  	  __REACT_DEVTOOLS_GLOBAL_HOOK__.registerInternalModuleStop(new Error());
  	}
  	        
  	  })();
  	}
  	return useSyncExternalStoreWithSelector_development;
  }

  var hasRequiredWithSelector;

  function requireWithSelector () {
  	if (hasRequiredWithSelector) return withSelector.exports;
  	hasRequiredWithSelector = 1;

  	{
  	  withSelector.exports = requireUseSyncExternalStoreWithSelector_development();
  	}
  	return withSelector.exports;
  }

  var withSelectorExports = requireWithSelector();

  // src/index.ts
  var React = (
    // prettier-ignore
    // @ts-ignore
    "default" in ReactOriginal__namespace ? ReactOriginal__namespace["default"] : ReactOriginal__namespace
  );

  // src/components/Context.ts
  var ContextKey = Symbol.for(`react-redux-context`);
  var gT = typeof globalThis !== "undefined" ? globalThis : (
    /* fall back to a per-module scope (pre-8.1 behaviour) if `globalThis` is not available */
    {}
  );
  function getContext() {
    if (!React.createContext)
      return {};
    const contextMap = gT[ContextKey] ?? (gT[ContextKey] = /* @__PURE__ */ new Map());
    let realContext = contextMap.get(React.createContext);
    if (!realContext) {
      realContext = React.createContext(
        null
      );
      {
        realContext.displayName = "ReactRedux";
      }
      contextMap.set(React.createContext, realContext);
    }
    return realContext;
  }
  var ReactReduxContext = /* @__PURE__ */ getContext();

  // src/utils/useSyncExternalStore.ts
  var notInitialized = () => {
    throw new Error("uSES not initialized!");
  };

  // src/hooks/useReduxContext.ts
  function createReduxContextHook(context = ReactReduxContext) {
    return function useReduxContext2() {
      const contextValue = React.useContext(context);
      if (!contextValue) {
        throw new Error(
          "could not find react-redux context value; please ensure the component is wrapped in a <Provider>"
        );
      }
      return contextValue;
    };
  }
  var useReduxContext = /* @__PURE__ */ createReduxContextHook();

  // src/hooks/useSelector.ts
  var useSyncExternalStoreWithSelector = notInitialized;
  var initializeUseSelector = (fn) => {
    useSyncExternalStoreWithSelector = fn;
  };
  var refEquality = (a, b) => a === b;
  function createSelectorHook(context = ReactReduxContext) {
    const useReduxContext2 = context === ReactReduxContext ? useReduxContext : createReduxContextHook(context);
    const useSelector2 = (selector, equalityFnOrOptions = {}) => {
      const { equalityFn = refEquality, devModeChecks = {} } = typeof equalityFnOrOptions === "function" ? { equalityFn: equalityFnOrOptions } : equalityFnOrOptions;
      {
        if (!selector) {
          throw new Error(`You must pass a selector to useSelector`);
        }
        if (typeof selector !== "function") {
          throw new Error(`You must pass a function as a selector to useSelector`);
        }
        if (typeof equalityFn !== "function") {
          throw new Error(
            `You must pass a function as an equality function to useSelector`
          );
        }
      }
      const {
        store,
        subscription,
        getServerState,
        stabilityCheck,
        identityFunctionCheck
      } = useReduxContext2();
      const firstRun = React.useRef(true);
      const wrappedSelector = React.useCallback(
        {
          [selector.name](state) {
            const selected = selector(state);
            {
              const {
                identityFunctionCheck: finalIdentityFunctionCheck,
                stabilityCheck: finalStabilityCheck
              } = {
                stabilityCheck,
                identityFunctionCheck,
                ...devModeChecks
              };
              if (finalStabilityCheck === "always" || finalStabilityCheck === "once" && firstRun.current) {
                const toCompare = selector(state);
                if (!equalityFn(selected, toCompare)) {
                  let stack = void 0;
                  try {
                    throw new Error();
                  } catch (e) {
                    ({ stack } = e);
                  }
                  console.warn(
                    "Selector " + (selector.name || "unknown") + " returned a different result when called with the same parameters. This can lead to unnecessary rerenders.\nSelectors that return a new reference (such as an object or an array) should be memoized: https://redux.js.org/usage/deriving-data-selectors#optimizing-selectors-with-memoization",
                    {
                      state,
                      selected,
                      selected2: toCompare,
                      stack
                    }
                  );
                }
              }
              if (finalIdentityFunctionCheck === "always" || finalIdentityFunctionCheck === "once" && firstRun.current) {
                if (selected === state) {
                  let stack = void 0;
                  try {
                    throw new Error();
                  } catch (e) {
                    ({ stack } = e);
                  }
                  console.warn(
                    "Selector " + (selector.name || "unknown") + " returned the root state when called. This can lead to unnecessary rerenders.\nSelectors that return the entire state are almost certainly a mistake, as they will cause a rerender whenever *anything* in state changes.",
                    { stack }
                  );
                }
              }
              if (firstRun.current)
                firstRun.current = false;
            }
            return selected;
          }
        }[selector.name],
        [selector, stabilityCheck, devModeChecks.stabilityCheck]
      );
      const selectedState = useSyncExternalStoreWithSelector(
        subscription.addNestedSub,
        store.getState,
        getServerState || store.getState,
        wrappedSelector,
        equalityFn
      );
      React.useDebugValue(selectedState);
      return selectedState;
    };
    Object.assign(useSelector2, {
      withTypes: () => useSelector2
    });
    return useSelector2;
  }
  var useSelector = /* @__PURE__ */ createSelectorHook();

  // src/utils/batch.ts
  function defaultNoopBatch(callback) {
    callback();
  }

  // src/utils/Subscription.ts
  function createListenerCollection() {
    let first = null;
    let last = null;
    return {
      clear() {
        first = null;
        last = null;
      },
      notify() {
        defaultNoopBatch(() => {
          let listener = first;
          while (listener) {
            listener.callback();
            listener = listener.next;
          }
        });
      },
      get() {
        const listeners = [];
        let listener = first;
        while (listener) {
          listeners.push(listener);
          listener = listener.next;
        }
        return listeners;
      },
      subscribe(callback) {
        let isSubscribed = true;
        const listener = last = {
          callback,
          next: null,
          prev: last
        };
        if (listener.prev) {
          listener.prev.next = listener;
        } else {
          first = listener;
        }
        return function unsubscribe() {
          if (!isSubscribed || first === null)
            return;
          isSubscribed = false;
          if (listener.next) {
            listener.next.prev = listener.prev;
          } else {
            last = listener.prev;
          }
          if (listener.prev) {
            listener.prev.next = listener.next;
          } else {
            first = listener.next;
          }
        };
      }
    };
  }
  var nullListeners = {
    notify() {
    },
    get: () => []
  };
  function createSubscription(store, parentSub) {
    let unsubscribe;
    let listeners = nullListeners;
    let subscriptionsAmount = 0;
    let selfSubscribed = false;
    function addNestedSub(listener) {
      trySubscribe();
      const cleanupListener = listeners.subscribe(listener);
      let removed = false;
      return () => {
        if (!removed) {
          removed = true;
          cleanupListener();
          tryUnsubscribe();
        }
      };
    }
    function notifyNestedSubs() {
      listeners.notify();
    }
    function handleChangeWrapper() {
      if (subscription.onStateChange) {
        subscription.onStateChange();
      }
    }
    function isSubscribed() {
      return selfSubscribed;
    }
    function trySubscribe() {
      subscriptionsAmount++;
      if (!unsubscribe) {
        unsubscribe = parentSub ? parentSub.addNestedSub(handleChangeWrapper) : store.subscribe(handleChangeWrapper);
        listeners = createListenerCollection();
      }
    }
    function tryUnsubscribe() {
      subscriptionsAmount--;
      if (unsubscribe && subscriptionsAmount === 0) {
        unsubscribe();
        unsubscribe = void 0;
        listeners.clear();
        listeners = nullListeners;
      }
    }
    function trySubscribeSelf() {
      if (!selfSubscribed) {
        selfSubscribed = true;
        trySubscribe();
      }
    }
    function tryUnsubscribeSelf() {
      if (selfSubscribed) {
        selfSubscribed = false;
        tryUnsubscribe();
      }
    }
    const subscription = {
      addNestedSub,
      notifyNestedSubs,
      handleChangeWrapper,
      isSubscribed,
      trySubscribe: trySubscribeSelf,
      tryUnsubscribe: tryUnsubscribeSelf,
      getListeners: () => listeners
    };
    return subscription;
  }

  // src/utils/useIsomorphicLayoutEffect.ts
  var canUseDOM = !!(typeof window !== "undefined" && typeof window.document !== "undefined" && typeof window.document.createElement !== "undefined");
  var isReactNative = typeof navigator !== "undefined" && navigator.product === "ReactNative";
  var useIsomorphicLayoutEffect = canUseDOM || isReactNative ? React.useLayoutEffect : React.useEffect;

  // src/components/Provider.tsx
  function Provider({
    store,
    context,
    children,
    serverState,
    stabilityCheck = "once",
    identityFunctionCheck = "once"
  }) {
    const contextValue = React.useMemo(() => {
      const subscription = createSubscription(store);
      return {
        store,
        subscription,
        getServerState: serverState ? () => serverState : void 0,
        stabilityCheck,
        identityFunctionCheck
      };
    }, [store, serverState, stabilityCheck, identityFunctionCheck]);
    const previousState = React.useMemo(() => store.getState(), [store]);
    useIsomorphicLayoutEffect(() => {
      const { subscription } = contextValue;
      subscription.onStateChange = subscription.notifyNestedSubs;
      subscription.trySubscribe();
      if (previousState !== store.getState()) {
        subscription.notifyNestedSubs();
      }
      return () => {
        subscription.tryUnsubscribe();
        subscription.onStateChange = void 0;
      };
    }, [contextValue, previousState]);
    const Context = context || ReactReduxContext;
    return /* @__PURE__ */ React.createElement(Context.Provider, { value: contextValue }, children);
  }
  var Provider_default = Provider;

  // src/hooks/useStore.ts
  function createStoreHook(context = ReactReduxContext) {
    const useReduxContext2 = context === ReactReduxContext ? useReduxContext : (
      // @ts-ignore
      createReduxContextHook(context)
    );
    const useStore2 = () => {
      const { store } = useReduxContext2();
      return store;
    };
    Object.assign(useStore2, {
      withTypes: () => useStore2
    });
    return useStore2;
  }
  var useStore = /* @__PURE__ */ createStoreHook();

  // src/hooks/useDispatch.ts
  function createDispatchHook(context = ReactReduxContext) {
    const useStore2 = context === ReactReduxContext ? useStore : createStoreHook(context);
    const useDispatch2 = () => {
      const store = useStore2();
      return store.dispatch;
    };
    Object.assign(useDispatch2, {
      withTypes: () => useDispatch2
    });
    return useDispatch2;
  }
  var useDispatch = /* @__PURE__ */ createDispatchHook();

  // src/index.ts
  initializeUseSelector(withSelectorExports.useSyncExternalStoreWithSelector);

  var client = {};

  var hasRequiredClient;

  function requireClient () {
  	if (hasRequiredClient) return client;
  	hasRequiredClient = 1;

  	var m = require$$0__default["default"];
  	{
  	  var i = m.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  	  client.createRoot = function(c, o) {
  	    i.usingClientEntryPoint = true;
  	    try {
  	      return m.createRoot(c, o);
  	    } finally {
  	      i.usingClientEntryPoint = false;
  	    }
  	  };
  	  client.hydrateRoot = function(c, h, o) {
  	    i.usingClientEntryPoint = true;
  	    try {
  	      return m.hydrateRoot(c, h, o);
  	    } finally {
  	      i.usingClientEntryPoint = false;
  	    }
  	  };
  	}
  	return client;
  }

  var clientExports = requireClient();

  function _arrayLikeToArray(r, a) {
    (null == a || a > r.length) && (a = r.length);
    for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e];
    return n;
  }
  function _arrayWithHoles(r) {
    if (Array.isArray(r)) return r;
  }
  function _defineProperty(e, r, t) {
    return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, {
      value: t,
      enumerable: !0,
      configurable: !0,
      writable: !0
    }) : e[r] = t, e;
  }
  function _iterableToArrayLimit(r, l) {
    var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"];
    if (null != t) {
      var e,
        n,
        i,
        u,
        a = [],
        f = !0,
        o = !1;
      try {
        if (i = (t = t.call(r)).next, 0 === l) {
          if (Object(t) !== t) return;
          f = !1;
        } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0);
      } catch (r) {
        o = !0, n = r;
      } finally {
        try {
          if (!f && null != t.return && (u = t.return(), Object(u) !== u)) return;
        } finally {
          if (o) throw n;
        }
      }
      return a;
    }
  }
  function _nonIterableRest() {
    throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
  }
  function ownKeys(e, r) {
    var t = Object.keys(e);
    if (Object.getOwnPropertySymbols) {
      var o = Object.getOwnPropertySymbols(e);
      r && (o = o.filter(function (r) {
        return Object.getOwnPropertyDescriptor(e, r).enumerable;
      })), t.push.apply(t, o);
    }
    return t;
  }
  function _objectSpread2(e) {
    for (var r = 1; r < arguments.length; r++) {
      var t = null != arguments[r] ? arguments[r] : {};
      r % 2 ? ownKeys(Object(t), !0).forEach(function (r) {
        _defineProperty(e, r, t[r]);
      }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) {
        Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r));
      });
    }
    return e;
  }
  function _slicedToArray(r, e) {
    return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest();
  }
  function _toPrimitive(t, r) {
    if ("object" != typeof t || !t) return t;
    var e = t[Symbol.toPrimitive];
    if (void 0 !== e) {
      var i = e.call(t, r || "default");
      if ("object" != typeof i) return i;
      throw new TypeError("@@toPrimitive must return a primitive value.");
    }
    return ("string" === r ? String : Number)(t);
  }
  function _toPropertyKey(t) {
    var i = _toPrimitive(t, "string");
    return "symbol" == typeof i ? i : i + "";
  }
  function _typeof(o) {
    "@babel/helpers - typeof";

    return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
      return typeof o;
    } : function (o) {
      return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
    }, _typeof(o);
  }
  function _unsupportedIterableToArray(r, a) {
    if (r) {
      if ("string" == typeof r) return _arrayLikeToArray(r, a);
      var t = {}.toString.call(r).slice(8, -1);
      return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0;
    }
  }

  var jsxRuntime = {exports: {}};

  var reactJsxRuntime_development = {};

  /**
   * @license React
   * react-jsx-runtime.development.js
   *
   * Copyright (c) Facebook, Inc. and its affiliates.
   *
   * This source code is licensed under the MIT license found in the
   * LICENSE file in the root directory of this source tree.
   */

  var hasRequiredReactJsxRuntime_development;

  function requireReactJsxRuntime_development () {
  	if (hasRequiredReactJsxRuntime_development) return reactJsxRuntime_development;
  	hasRequiredReactJsxRuntime_development = 1;

  	{
  	  (function() {

  	var React = ReactOriginal__default["default"];

  	// ATTENTION
  	// When adding new symbols to this file,
  	// Please consider also adding to 'react-devtools-shared/src/backend/ReactSymbols'
  	// The Symbol used to tag the ReactElement-like types.
  	var REACT_ELEMENT_TYPE = Symbol.for('react.element');
  	var REACT_PORTAL_TYPE = Symbol.for('react.portal');
  	var REACT_FRAGMENT_TYPE = Symbol.for('react.fragment');
  	var REACT_STRICT_MODE_TYPE = Symbol.for('react.strict_mode');
  	var REACT_PROFILER_TYPE = Symbol.for('react.profiler');
  	var REACT_PROVIDER_TYPE = Symbol.for('react.provider');
  	var REACT_CONTEXT_TYPE = Symbol.for('react.context');
  	var REACT_FORWARD_REF_TYPE = Symbol.for('react.forward_ref');
  	var REACT_SUSPENSE_TYPE = Symbol.for('react.suspense');
  	var REACT_SUSPENSE_LIST_TYPE = Symbol.for('react.suspense_list');
  	var REACT_MEMO_TYPE = Symbol.for('react.memo');
  	var REACT_LAZY_TYPE = Symbol.for('react.lazy');
  	var REACT_OFFSCREEN_TYPE = Symbol.for('react.offscreen');
  	var MAYBE_ITERATOR_SYMBOL = Symbol.iterator;
  	var FAUX_ITERATOR_SYMBOL = '@@iterator';
  	function getIteratorFn(maybeIterable) {
  	  if (maybeIterable === null || typeof maybeIterable !== 'object') {
  	    return null;
  	  }

  	  var maybeIterator = MAYBE_ITERATOR_SYMBOL && maybeIterable[MAYBE_ITERATOR_SYMBOL] || maybeIterable[FAUX_ITERATOR_SYMBOL];

  	  if (typeof maybeIterator === 'function') {
  	    return maybeIterator;
  	  }

  	  return null;
  	}

  	var ReactSharedInternals = React.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;

  	function error(format) {
  	  {
  	    {
  	      for (var _len2 = arguments.length, args = new Array(_len2 > 1 ? _len2 - 1 : 0), _key2 = 1; _key2 < _len2; _key2++) {
  	        args[_key2 - 1] = arguments[_key2];
  	      }

  	      printWarning('error', format, args);
  	    }
  	  }
  	}

  	function printWarning(level, format, args) {
  	  // When changing this logic, you might want to also
  	  // update consoleWithStackDev.www.js as well.
  	  {
  	    var ReactDebugCurrentFrame = ReactSharedInternals.ReactDebugCurrentFrame;
  	    var stack = ReactDebugCurrentFrame.getStackAddendum();

  	    if (stack !== '') {
  	      format += '%s';
  	      args = args.concat([stack]);
  	    } // eslint-disable-next-line react-internal/safe-string-coercion


  	    var argsWithFormat = args.map(function (item) {
  	      return String(item);
  	    }); // Careful: RN currently depends on this prefix

  	    argsWithFormat.unshift('Warning: ' + format); // We intentionally don't use spread (or .apply) directly because it
  	    // breaks IE9: https://github.com/facebook/react/issues/13610
  	    // eslint-disable-next-line react-internal/no-production-logging

  	    Function.prototype.apply.call(console[level], console, argsWithFormat);
  	  }
  	}

  	// -----------------------------------------------------------------------------

  	var enableScopeAPI = false; // Experimental Create Event Handle API.
  	var enableCacheElement = false;
  	var enableTransitionTracing = false; // No known bugs, but needs performance testing

  	var enableLegacyHidden = false; // Enables unstable_avoidThisFallback feature in Fiber
  	// stuff. Intended to enable React core members to more easily debug scheduling
  	// issues in DEV builds.

  	var enableDebugTracing = false; // Track which Fiber(s) schedule render work.

  	var REACT_MODULE_REFERENCE;

  	{
  	  REACT_MODULE_REFERENCE = Symbol.for('react.module.reference');
  	}

  	function isValidElementType(type) {
  	  if (typeof type === 'string' || typeof type === 'function') {
  	    return true;
  	  } // Note: typeof might be other than 'symbol' or 'number' (e.g. if it's a polyfill).


  	  if (type === REACT_FRAGMENT_TYPE || type === REACT_PROFILER_TYPE || enableDebugTracing  || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || enableLegacyHidden  || type === REACT_OFFSCREEN_TYPE || enableScopeAPI  || enableCacheElement  || enableTransitionTracing ) {
  	    return true;
  	  }

  	  if (typeof type === 'object' && type !== null) {
  	    if (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_PROVIDER_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || // This needs to include all possible module reference object
  	    // types supported by any Flight configuration anywhere since
  	    // we don't know which Flight build this will end up being used
  	    // with.
  	    type.$$typeof === REACT_MODULE_REFERENCE || type.getModuleId !== undefined) {
  	      return true;
  	    }
  	  }

  	  return false;
  	}

  	function getWrappedName(outerType, innerType, wrapperName) {
  	  var displayName = outerType.displayName;

  	  if (displayName) {
  	    return displayName;
  	  }

  	  var functionName = innerType.displayName || innerType.name || '';
  	  return functionName !== '' ? wrapperName + "(" + functionName + ")" : wrapperName;
  	} // Keep in sync with react-reconciler/getComponentNameFromFiber


  	function getContextName(type) {
  	  return type.displayName || 'Context';
  	} // Note that the reconciler package should generally prefer to use getComponentNameFromFiber() instead.


  	function getComponentNameFromType(type) {
  	  if (type == null) {
  	    // Host root, text node or just invalid type.
  	    return null;
  	  }

  	  {
  	    if (typeof type.tag === 'number') {
  	      error('Received an unexpected object in getComponentNameFromType(). ' + 'This is likely a bug in React. Please file an issue.');
  	    }
  	  }

  	  if (typeof type === 'function') {
  	    return type.displayName || type.name || null;
  	  }

  	  if (typeof type === 'string') {
  	    return type;
  	  }

  	  switch (type) {
  	    case REACT_FRAGMENT_TYPE:
  	      return 'Fragment';

  	    case REACT_PORTAL_TYPE:
  	      return 'Portal';

  	    case REACT_PROFILER_TYPE:
  	      return 'Profiler';

  	    case REACT_STRICT_MODE_TYPE:
  	      return 'StrictMode';

  	    case REACT_SUSPENSE_TYPE:
  	      return 'Suspense';

  	    case REACT_SUSPENSE_LIST_TYPE:
  	      return 'SuspenseList';

  	  }

  	  if (typeof type === 'object') {
  	    switch (type.$$typeof) {
  	      case REACT_CONTEXT_TYPE:
  	        var context = type;
  	        return getContextName(context) + '.Consumer';

  	      case REACT_PROVIDER_TYPE:
  	        var provider = type;
  	        return getContextName(provider._context) + '.Provider';

  	      case REACT_FORWARD_REF_TYPE:
  	        return getWrappedName(type, type.render, 'ForwardRef');

  	      case REACT_MEMO_TYPE:
  	        var outerName = type.displayName || null;

  	        if (outerName !== null) {
  	          return outerName;
  	        }

  	        return getComponentNameFromType(type.type) || 'Memo';

  	      case REACT_LAZY_TYPE:
  	        {
  	          var lazyComponent = type;
  	          var payload = lazyComponent._payload;
  	          var init = lazyComponent._init;

  	          try {
  	            return getComponentNameFromType(init(payload));
  	          } catch (x) {
  	            return null;
  	          }
  	        }

  	      // eslint-disable-next-line no-fallthrough
  	    }
  	  }

  	  return null;
  	}

  	var assign = Object.assign;

  	// Helpers to patch console.logs to avoid logging during side-effect free
  	// replaying on render function. This currently only patches the object
  	// lazily which won't cover if the log function was extracted eagerly.
  	// We could also eagerly patch the method.
  	var disabledDepth = 0;
  	var prevLog;
  	var prevInfo;
  	var prevWarn;
  	var prevError;
  	var prevGroup;
  	var prevGroupCollapsed;
  	var prevGroupEnd;

  	function disabledLog() {}

  	disabledLog.__reactDisabledLog = true;
  	function disableLogs() {
  	  {
  	    if (disabledDepth === 0) {
  	      /* eslint-disable react-internal/no-production-logging */
  	      prevLog = console.log;
  	      prevInfo = console.info;
  	      prevWarn = console.warn;
  	      prevError = console.error;
  	      prevGroup = console.group;
  	      prevGroupCollapsed = console.groupCollapsed;
  	      prevGroupEnd = console.groupEnd; // https://github.com/facebook/react/issues/19099

  	      var props = {
  	        configurable: true,
  	        enumerable: true,
  	        value: disabledLog,
  	        writable: true
  	      }; // $FlowFixMe Flow thinks console is immutable.

  	      Object.defineProperties(console, {
  	        info: props,
  	        log: props,
  	        warn: props,
  	        error: props,
  	        group: props,
  	        groupCollapsed: props,
  	        groupEnd: props
  	      });
  	      /* eslint-enable react-internal/no-production-logging */
  	    }

  	    disabledDepth++;
  	  }
  	}
  	function reenableLogs() {
  	  {
  	    disabledDepth--;

  	    if (disabledDepth === 0) {
  	      /* eslint-disable react-internal/no-production-logging */
  	      var props = {
  	        configurable: true,
  	        enumerable: true,
  	        writable: true
  	      }; // $FlowFixMe Flow thinks console is immutable.

  	      Object.defineProperties(console, {
  	        log: assign({}, props, {
  	          value: prevLog
  	        }),
  	        info: assign({}, props, {
  	          value: prevInfo
  	        }),
  	        warn: assign({}, props, {
  	          value: prevWarn
  	        }),
  	        error: assign({}, props, {
  	          value: prevError
  	        }),
  	        group: assign({}, props, {
  	          value: prevGroup
  	        }),
  	        groupCollapsed: assign({}, props, {
  	          value: prevGroupCollapsed
  	        }),
  	        groupEnd: assign({}, props, {
  	          value: prevGroupEnd
  	        })
  	      });
  	      /* eslint-enable react-internal/no-production-logging */
  	    }

  	    if (disabledDepth < 0) {
  	      error('disabledDepth fell below zero. ' + 'This is a bug in React. Please file an issue.');
  	    }
  	  }
  	}

  	var ReactCurrentDispatcher = ReactSharedInternals.ReactCurrentDispatcher;
  	var prefix;
  	function describeBuiltInComponentFrame(name, source, ownerFn) {
  	  {
  	    if (prefix === undefined) {
  	      // Extract the VM specific prefix used by each line.
  	      try {
  	        throw Error();
  	      } catch (x) {
  	        var match = x.stack.trim().match(/\n( *(at )?)/);
  	        prefix = match && match[1] || '';
  	      }
  	    } // We use the prefix to ensure our stacks line up with native stack frames.


  	    return '\n' + prefix + name;
  	  }
  	}
  	var reentry = false;
  	var componentFrameCache;

  	{
  	  var PossiblyWeakMap = typeof WeakMap === 'function' ? WeakMap : Map;
  	  componentFrameCache = new PossiblyWeakMap();
  	}

  	function describeNativeComponentFrame(fn, construct) {
  	  // If something asked for a stack inside a fake render, it should get ignored.
  	  if ( !fn || reentry) {
  	    return '';
  	  }

  	  {
  	    var frame = componentFrameCache.get(fn);

  	    if (frame !== undefined) {
  	      return frame;
  	    }
  	  }

  	  var control;
  	  reentry = true;
  	  var previousPrepareStackTrace = Error.prepareStackTrace; // $FlowFixMe It does accept undefined.

  	  Error.prepareStackTrace = undefined;
  	  var previousDispatcher;

  	  {
  	    previousDispatcher = ReactCurrentDispatcher.current; // Set the dispatcher in DEV because this might be call in the render function
  	    // for warnings.

  	    ReactCurrentDispatcher.current = null;
  	    disableLogs();
  	  }

  	  try {
  	    // This should throw.
  	    if (construct) {
  	      // Something should be setting the props in the constructor.
  	      var Fake = function () {
  	        throw Error();
  	      }; // $FlowFixMe


  	      Object.defineProperty(Fake.prototype, 'props', {
  	        set: function () {
  	          // We use a throwing setter instead of frozen or non-writable props
  	          // because that won't throw in a non-strict mode function.
  	          throw Error();
  	        }
  	      });

  	      if (typeof Reflect === 'object' && Reflect.construct) {
  	        // We construct a different control for this case to include any extra
  	        // frames added by the construct call.
  	        try {
  	          Reflect.construct(Fake, []);
  	        } catch (x) {
  	          control = x;
  	        }

  	        Reflect.construct(fn, [], Fake);
  	      } else {
  	        try {
  	          Fake.call();
  	        } catch (x) {
  	          control = x;
  	        }

  	        fn.call(Fake.prototype);
  	      }
  	    } else {
  	      try {
  	        throw Error();
  	      } catch (x) {
  	        control = x;
  	      }

  	      fn();
  	    }
  	  } catch (sample) {
  	    // This is inlined manually because closure doesn't do it for us.
  	    if (sample && control && typeof sample.stack === 'string') {
  	      // This extracts the first frame from the sample that isn't also in the control.
  	      // Skipping one frame that we assume is the frame that calls the two.
  	      var sampleLines = sample.stack.split('\n');
  	      var controlLines = control.stack.split('\n');
  	      var s = sampleLines.length - 1;
  	      var c = controlLines.length - 1;

  	      while (s >= 1 && c >= 0 && sampleLines[s] !== controlLines[c]) {
  	        // We expect at least one stack frame to be shared.
  	        // Typically this will be the root most one. However, stack frames may be
  	        // cut off due to maximum stack limits. In this case, one maybe cut off
  	        // earlier than the other. We assume that the sample is longer or the same
  	        // and there for cut off earlier. So we should find the root most frame in
  	        // the sample somewhere in the control.
  	        c--;
  	      }

  	      for (; s >= 1 && c >= 0; s--, c--) {
  	        // Next we find the first one that isn't the same which should be the
  	        // frame that called our sample function and the control.
  	        if (sampleLines[s] !== controlLines[c]) {
  	          // In V8, the first line is describing the message but other VMs don't.
  	          // If we're about to return the first line, and the control is also on the same
  	          // line, that's a pretty good indicator that our sample threw at same line as
  	          // the control. I.e. before we entered the sample frame. So we ignore this result.
  	          // This can happen if you passed a class to function component, or non-function.
  	          if (s !== 1 || c !== 1) {
  	            do {
  	              s--;
  	              c--; // We may still have similar intermediate frames from the construct call.
  	              // The next one that isn't the same should be our match though.

  	              if (c < 0 || sampleLines[s] !== controlLines[c]) {
  	                // V8 adds a "new" prefix for native classes. Let's remove it to make it prettier.
  	                var _frame = '\n' + sampleLines[s].replace(' at new ', ' at '); // If our component frame is labeled "<anonymous>"
  	                // but we have a user-provided "displayName"
  	                // splice it in to make the stack more readable.


  	                if (fn.displayName && _frame.includes('<anonymous>')) {
  	                  _frame = _frame.replace('<anonymous>', fn.displayName);
  	                }

  	                {
  	                  if (typeof fn === 'function') {
  	                    componentFrameCache.set(fn, _frame);
  	                  }
  	                } // Return the line we found.


  	                return _frame;
  	              }
  	            } while (s >= 1 && c >= 0);
  	          }

  	          break;
  	        }
  	      }
  	    }
  	  } finally {
  	    reentry = false;

  	    {
  	      ReactCurrentDispatcher.current = previousDispatcher;
  	      reenableLogs();
  	    }

  	    Error.prepareStackTrace = previousPrepareStackTrace;
  	  } // Fallback to just using the name if we couldn't make it throw.


  	  var name = fn ? fn.displayName || fn.name : '';
  	  var syntheticFrame = name ? describeBuiltInComponentFrame(name) : '';

  	  {
  	    if (typeof fn === 'function') {
  	      componentFrameCache.set(fn, syntheticFrame);
  	    }
  	  }

  	  return syntheticFrame;
  	}
  	function describeFunctionComponentFrame(fn, source, ownerFn) {
  	  {
  	    return describeNativeComponentFrame(fn, false);
  	  }
  	}

  	function shouldConstruct(Component) {
  	  var prototype = Component.prototype;
  	  return !!(prototype && prototype.isReactComponent);
  	}

  	function describeUnknownElementTypeFrameInDEV(type, source, ownerFn) {

  	  if (type == null) {
  	    return '';
  	  }

  	  if (typeof type === 'function') {
  	    {
  	      return describeNativeComponentFrame(type, shouldConstruct(type));
  	    }
  	  }

  	  if (typeof type === 'string') {
  	    return describeBuiltInComponentFrame(type);
  	  }

  	  switch (type) {
  	    case REACT_SUSPENSE_TYPE:
  	      return describeBuiltInComponentFrame('Suspense');

  	    case REACT_SUSPENSE_LIST_TYPE:
  	      return describeBuiltInComponentFrame('SuspenseList');
  	  }

  	  if (typeof type === 'object') {
  	    switch (type.$$typeof) {
  	      case REACT_FORWARD_REF_TYPE:
  	        return describeFunctionComponentFrame(type.render);

  	      case REACT_MEMO_TYPE:
  	        // Memo may contain any component type so we recursively resolve it.
  	        return describeUnknownElementTypeFrameInDEV(type.type, source, ownerFn);

  	      case REACT_LAZY_TYPE:
  	        {
  	          var lazyComponent = type;
  	          var payload = lazyComponent._payload;
  	          var init = lazyComponent._init;

  	          try {
  	            // Lazy may contain any component type so we recursively resolve it.
  	            return describeUnknownElementTypeFrameInDEV(init(payload), source, ownerFn);
  	          } catch (x) {}
  	        }
  	    }
  	  }

  	  return '';
  	}

  	var hasOwnProperty = Object.prototype.hasOwnProperty;

  	var loggedTypeFailures = {};
  	var ReactDebugCurrentFrame = ReactSharedInternals.ReactDebugCurrentFrame;

  	function setCurrentlyValidatingElement(element) {
  	  {
  	    if (element) {
  	      var owner = element._owner;
  	      var stack = describeUnknownElementTypeFrameInDEV(element.type, element._source, owner ? owner.type : null);
  	      ReactDebugCurrentFrame.setExtraStackFrame(stack);
  	    } else {
  	      ReactDebugCurrentFrame.setExtraStackFrame(null);
  	    }
  	  }
  	}

  	function checkPropTypes(typeSpecs, values, location, componentName, element) {
  	  {
  	    // $FlowFixMe This is okay but Flow doesn't know it.
  	    var has = Function.call.bind(hasOwnProperty);

  	    for (var typeSpecName in typeSpecs) {
  	      if (has(typeSpecs, typeSpecName)) {
  	        var error$1 = void 0; // Prop type validation may throw. In case they do, we don't want to
  	        // fail the render phase where it didn't fail before. So we log it.
  	        // After these have been cleaned up, we'll let them throw.

  	        try {
  	          // This is intentionally an invariant that gets caught. It's the same
  	          // behavior as without this statement except with a better message.
  	          if (typeof typeSpecs[typeSpecName] !== 'function') {
  	            // eslint-disable-next-line react-internal/prod-error-codes
  	            var err = Error((componentName || 'React class') + ': ' + location + ' type `' + typeSpecName + '` is invalid; ' + 'it must be a function, usually from the `prop-types` package, but received `' + typeof typeSpecs[typeSpecName] + '`.' + 'This often happens because of typos such as `PropTypes.function` instead of `PropTypes.func`.');
  	            err.name = 'Invariant Violation';
  	            throw err;
  	          }

  	          error$1 = typeSpecs[typeSpecName](values, typeSpecName, componentName, location, null, 'SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED');
  	        } catch (ex) {
  	          error$1 = ex;
  	        }

  	        if (error$1 && !(error$1 instanceof Error)) {
  	          setCurrentlyValidatingElement(element);

  	          error('%s: type specification of %s' + ' `%s` is invalid; the type checker ' + 'function must return `null` or an `Error` but returned a %s. ' + 'You may have forgotten to pass an argument to the type checker ' + 'creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and ' + 'shape all require an argument).', componentName || 'React class', location, typeSpecName, typeof error$1);

  	          setCurrentlyValidatingElement(null);
  	        }

  	        if (error$1 instanceof Error && !(error$1.message in loggedTypeFailures)) {
  	          // Only monitor this failure once because there tends to be a lot of the
  	          // same error.
  	          loggedTypeFailures[error$1.message] = true;
  	          setCurrentlyValidatingElement(element);

  	          error('Failed %s type: %s', location, error$1.message);

  	          setCurrentlyValidatingElement(null);
  	        }
  	      }
  	    }
  	  }
  	}

  	var isArrayImpl = Array.isArray; // eslint-disable-next-line no-redeclare

  	function isArray(a) {
  	  return isArrayImpl(a);
  	}

  	/*
  	 * The `'' + value` pattern (used in in perf-sensitive code) throws for Symbol
  	 * and Temporal.* types. See https://github.com/facebook/react/pull/22064.
  	 *
  	 * The functions in this module will throw an easier-to-understand,
  	 * easier-to-debug exception with a clear errors message message explaining the
  	 * problem. (Instead of a confusing exception thrown inside the implementation
  	 * of the `value` object).
  	 */
  	// $FlowFixMe only called in DEV, so void return is not possible.
  	function typeName(value) {
  	  {
  	    // toStringTag is needed for namespaced types like Temporal.Instant
  	    var hasToStringTag = typeof Symbol === 'function' && Symbol.toStringTag;
  	    var type = hasToStringTag && value[Symbol.toStringTag] || value.constructor.name || 'Object';
  	    return type;
  	  }
  	} // $FlowFixMe only called in DEV, so void return is not possible.


  	function willCoercionThrow(value) {
  	  {
  	    try {
  	      testStringCoercion(value);
  	      return false;
  	    } catch (e) {
  	      return true;
  	    }
  	  }
  	}

  	function testStringCoercion(value) {
  	  // If you ended up here by following an exception call stack, here's what's
  	  // happened: you supplied an object or symbol value to React (as a prop, key,
  	  // DOM attribute, CSS property, string ref, etc.) and when React tried to
  	  // coerce it to a string using `'' + value`, an exception was thrown.
  	  //
  	  // The most common types that will cause this exception are `Symbol` instances
  	  // and Temporal objects like `Temporal.Instant`. But any object that has a
  	  // `valueOf` or `[Symbol.toPrimitive]` method that throws will also cause this
  	  // exception. (Library authors do this to prevent users from using built-in
  	  // numeric operators like `+` or comparison operators like `>=` because custom
  	  // methods are needed to perform accurate arithmetic or comparison.)
  	  //
  	  // To fix the problem, coerce this object or symbol value to a string before
  	  // passing it to React. The most reliable way is usually `String(value)`.
  	  //
  	  // To find which value is throwing, check the browser or debugger console.
  	  // Before this exception was thrown, there should be `console.error` output
  	  // that shows the type (Symbol, Temporal.PlainDate, etc.) that caused the
  	  // problem and how that type was used: key, atrribute, input value prop, etc.
  	  // In most cases, this console output also shows the component and its
  	  // ancestor components where the exception happened.
  	  //
  	  // eslint-disable-next-line react-internal/safe-string-coercion
  	  return '' + value;
  	}
  	function checkKeyStringCoercion(value) {
  	  {
  	    if (willCoercionThrow(value)) {
  	      error('The provided key is an unsupported type %s.' + ' This value must be coerced to a string before before using it here.', typeName(value));

  	      return testStringCoercion(value); // throw (to help callers find troubleshooting comments)
  	    }
  	  }
  	}

  	var ReactCurrentOwner = ReactSharedInternals.ReactCurrentOwner;
  	var RESERVED_PROPS = {
  	  key: true,
  	  ref: true,
  	  __self: true,
  	  __source: true
  	};
  	var specialPropKeyWarningShown;
  	var specialPropRefWarningShown;
  	var didWarnAboutStringRefs;

  	{
  	  didWarnAboutStringRefs = {};
  	}

  	function hasValidRef(config) {
  	  {
  	    if (hasOwnProperty.call(config, 'ref')) {
  	      var getter = Object.getOwnPropertyDescriptor(config, 'ref').get;

  	      if (getter && getter.isReactWarning) {
  	        return false;
  	      }
  	    }
  	  }

  	  return config.ref !== undefined;
  	}

  	function hasValidKey(config) {
  	  {
  	    if (hasOwnProperty.call(config, 'key')) {
  	      var getter = Object.getOwnPropertyDescriptor(config, 'key').get;

  	      if (getter && getter.isReactWarning) {
  	        return false;
  	      }
  	    }
  	  }

  	  return config.key !== undefined;
  	}

  	function warnIfStringRefCannotBeAutoConverted(config, self) {
  	  {
  	    if (typeof config.ref === 'string' && ReactCurrentOwner.current && self && ReactCurrentOwner.current.stateNode !== self) {
  	      var componentName = getComponentNameFromType(ReactCurrentOwner.current.type);

  	      if (!didWarnAboutStringRefs[componentName]) {
  	        error('Component "%s" contains the string ref "%s". ' + 'Support for string refs will be removed in a future major release. ' + 'This case cannot be automatically converted to an arrow function. ' + 'We ask you to manually fix this case by using useRef() or createRef() instead. ' + 'Learn more about using refs safely here: ' + 'https://reactjs.org/link/strict-mode-string-ref', getComponentNameFromType(ReactCurrentOwner.current.type), config.ref);

  	        didWarnAboutStringRefs[componentName] = true;
  	      }
  	    }
  	  }
  	}

  	function defineKeyPropWarningGetter(props, displayName) {
  	  {
  	    var warnAboutAccessingKey = function () {
  	      if (!specialPropKeyWarningShown) {
  	        specialPropKeyWarningShown = true;

  	        error('%s: `key` is not a prop. Trying to access it will result ' + 'in `undefined` being returned. If you need to access the same ' + 'value within the child component, you should pass it as a different ' + 'prop. (https://reactjs.org/link/special-props)', displayName);
  	      }
  	    };

  	    warnAboutAccessingKey.isReactWarning = true;
  	    Object.defineProperty(props, 'key', {
  	      get: warnAboutAccessingKey,
  	      configurable: true
  	    });
  	  }
  	}

  	function defineRefPropWarningGetter(props, displayName) {
  	  {
  	    var warnAboutAccessingRef = function () {
  	      if (!specialPropRefWarningShown) {
  	        specialPropRefWarningShown = true;

  	        error('%s: `ref` is not a prop. Trying to access it will result ' + 'in `undefined` being returned. If you need to access the same ' + 'value within the child component, you should pass it as a different ' + 'prop. (https://reactjs.org/link/special-props)', displayName);
  	      }
  	    };

  	    warnAboutAccessingRef.isReactWarning = true;
  	    Object.defineProperty(props, 'ref', {
  	      get: warnAboutAccessingRef,
  	      configurable: true
  	    });
  	  }
  	}
  	/**
  	 * Factory method to create a new React element. This no longer adheres to
  	 * the class pattern, so do not use new to call it. Also, instanceof check
  	 * will not work. Instead test $$typeof field against Symbol.for('react.element') to check
  	 * if something is a React Element.
  	 *
  	 * @param {*} type
  	 * @param {*} props
  	 * @param {*} key
  	 * @param {string|object} ref
  	 * @param {*} owner
  	 * @param {*} self A *temporary* helper to detect places where `this` is
  	 * different from the `owner` when React.createElement is called, so that we
  	 * can warn. We want to get rid of owner and replace string `ref`s with arrow
  	 * functions, and as long as `this` and owner are the same, there will be no
  	 * change in behavior.
  	 * @param {*} source An annotation object (added by a transpiler or otherwise)
  	 * indicating filename, line number, and/or other information.
  	 * @internal
  	 */


  	var ReactElement = function (type, key, ref, self, source, owner, props) {
  	  var element = {
  	    // This tag allows us to uniquely identify this as a React Element
  	    $$typeof: REACT_ELEMENT_TYPE,
  	    // Built-in properties that belong on the element
  	    type: type,
  	    key: key,
  	    ref: ref,
  	    props: props,
  	    // Record the component responsible for creating this element.
  	    _owner: owner
  	  };

  	  {
  	    // The validation flag is currently mutative. We put it on
  	    // an external backing store so that we can freeze the whole object.
  	    // This can be replaced with a WeakMap once they are implemented in
  	    // commonly used development environments.
  	    element._store = {}; // To make comparing ReactElements easier for testing purposes, we make
  	    // the validation flag non-enumerable (where possible, which should
  	    // include every environment we run tests in), so the test framework
  	    // ignores it.

  	    Object.defineProperty(element._store, 'validated', {
  	      configurable: false,
  	      enumerable: false,
  	      writable: true,
  	      value: false
  	    }); // self and source are DEV only properties.

  	    Object.defineProperty(element, '_self', {
  	      configurable: false,
  	      enumerable: false,
  	      writable: false,
  	      value: self
  	    }); // Two elements created in two different places should be considered
  	    // equal for testing purposes and therefore we hide it from enumeration.

  	    Object.defineProperty(element, '_source', {
  	      configurable: false,
  	      enumerable: false,
  	      writable: false,
  	      value: source
  	    });

  	    if (Object.freeze) {
  	      Object.freeze(element.props);
  	      Object.freeze(element);
  	    }
  	  }

  	  return element;
  	};
  	/**
  	 * https://github.com/reactjs/rfcs/pull/107
  	 * @param {*} type
  	 * @param {object} props
  	 * @param {string} key
  	 */

  	function jsxDEV(type, config, maybeKey, source, self) {
  	  {
  	    var propName; // Reserved names are extracted

  	    var props = {};
  	    var key = null;
  	    var ref = null; // Currently, key can be spread in as a prop. This causes a potential
  	    // issue if key is also explicitly declared (ie. <div {...props} key="Hi" />
  	    // or <div key="Hi" {...props} /> ). We want to deprecate key spread,
  	    // but as an intermediary step, we will use jsxDEV for everything except
  	    // <div {...props} key="Hi" />, because we aren't currently able to tell if
  	    // key is explicitly declared to be undefined or not.

  	    if (maybeKey !== undefined) {
  	      {
  	        checkKeyStringCoercion(maybeKey);
  	      }

  	      key = '' + maybeKey;
  	    }

  	    if (hasValidKey(config)) {
  	      {
  	        checkKeyStringCoercion(config.key);
  	      }

  	      key = '' + config.key;
  	    }

  	    if (hasValidRef(config)) {
  	      ref = config.ref;
  	      warnIfStringRefCannotBeAutoConverted(config, self);
  	    } // Remaining properties are added to a new props object


  	    for (propName in config) {
  	      if (hasOwnProperty.call(config, propName) && !RESERVED_PROPS.hasOwnProperty(propName)) {
  	        props[propName] = config[propName];
  	      }
  	    } // Resolve default props


  	    if (type && type.defaultProps) {
  	      var defaultProps = type.defaultProps;

  	      for (propName in defaultProps) {
  	        if (props[propName] === undefined) {
  	          props[propName] = defaultProps[propName];
  	        }
  	      }
  	    }

  	    if (key || ref) {
  	      var displayName = typeof type === 'function' ? type.displayName || type.name || 'Unknown' : type;

  	      if (key) {
  	        defineKeyPropWarningGetter(props, displayName);
  	      }

  	      if (ref) {
  	        defineRefPropWarningGetter(props, displayName);
  	      }
  	    }

  	    return ReactElement(type, key, ref, self, source, ReactCurrentOwner.current, props);
  	  }
  	}

  	var ReactCurrentOwner$1 = ReactSharedInternals.ReactCurrentOwner;
  	var ReactDebugCurrentFrame$1 = ReactSharedInternals.ReactDebugCurrentFrame;

  	function setCurrentlyValidatingElement$1(element) {
  	  {
  	    if (element) {
  	      var owner = element._owner;
  	      var stack = describeUnknownElementTypeFrameInDEV(element.type, element._source, owner ? owner.type : null);
  	      ReactDebugCurrentFrame$1.setExtraStackFrame(stack);
  	    } else {
  	      ReactDebugCurrentFrame$1.setExtraStackFrame(null);
  	    }
  	  }
  	}

  	var propTypesMisspellWarningShown;

  	{
  	  propTypesMisspellWarningShown = false;
  	}
  	/**
  	 * Verifies the object is a ReactElement.
  	 * See https://reactjs.org/docs/react-api.html#isvalidelement
  	 * @param {?object} object
  	 * @return {boolean} True if `object` is a ReactElement.
  	 * @final
  	 */


  	function isValidElement(object) {
  	  {
  	    return typeof object === 'object' && object !== null && object.$$typeof === REACT_ELEMENT_TYPE;
  	  }
  	}

  	function getDeclarationErrorAddendum() {
  	  {
  	    if (ReactCurrentOwner$1.current) {
  	      var name = getComponentNameFromType(ReactCurrentOwner$1.current.type);

  	      if (name) {
  	        return '\n\nCheck the render method of `' + name + '`.';
  	      }
  	    }

  	    return '';
  	  }
  	}

  	function getSourceInfoErrorAddendum(source) {
  	  {
  	    if (source !== undefined) {
  	      var fileName = source.fileName.replace(/^.*[\\\/]/, '');
  	      var lineNumber = source.lineNumber;
  	      return '\n\nCheck your code at ' + fileName + ':' + lineNumber + '.';
  	    }

  	    return '';
  	  }
  	}
  	/**
  	 * Warn if there's no key explicitly set on dynamic arrays of children or
  	 * object keys are not valid. This allows us to keep track of children between
  	 * updates.
  	 */


  	var ownerHasKeyUseWarning = {};

  	function getCurrentComponentErrorInfo(parentType) {
  	  {
  	    var info = getDeclarationErrorAddendum();

  	    if (!info) {
  	      var parentName = typeof parentType === 'string' ? parentType : parentType.displayName || parentType.name;

  	      if (parentName) {
  	        info = "\n\nCheck the top-level render call using <" + parentName + ">.";
  	      }
  	    }

  	    return info;
  	  }
  	}
  	/**
  	 * Warn if the element doesn't have an explicit key assigned to it.
  	 * This element is in an array. The array could grow and shrink or be
  	 * reordered. All children that haven't already been validated are required to
  	 * have a "key" property assigned to it. Error statuses are cached so a warning
  	 * will only be shown once.
  	 *
  	 * @internal
  	 * @param {ReactElement} element Element that requires a key.
  	 * @param {*} parentType element's parent's type.
  	 */


  	function validateExplicitKey(element, parentType) {
  	  {
  	    if (!element._store || element._store.validated || element.key != null) {
  	      return;
  	    }

  	    element._store.validated = true;
  	    var currentComponentErrorInfo = getCurrentComponentErrorInfo(parentType);

  	    if (ownerHasKeyUseWarning[currentComponentErrorInfo]) {
  	      return;
  	    }

  	    ownerHasKeyUseWarning[currentComponentErrorInfo] = true; // Usually the current owner is the offender, but if it accepts children as a
  	    // property, it may be the creator of the child that's responsible for
  	    // assigning it a key.

  	    var childOwner = '';

  	    if (element && element._owner && element._owner !== ReactCurrentOwner$1.current) {
  	      // Give the component that originally created this child.
  	      childOwner = " It was passed a child from " + getComponentNameFromType(element._owner.type) + ".";
  	    }

  	    setCurrentlyValidatingElement$1(element);

  	    error('Each child in a list should have a unique "key" prop.' + '%s%s See https://reactjs.org/link/warning-keys for more information.', currentComponentErrorInfo, childOwner);

  	    setCurrentlyValidatingElement$1(null);
  	  }
  	}
  	/**
  	 * Ensure that every element either is passed in a static location, in an
  	 * array with an explicit keys property defined, or in an object literal
  	 * with valid key property.
  	 *
  	 * @internal
  	 * @param {ReactNode} node Statically passed child of any type.
  	 * @param {*} parentType node's parent's type.
  	 */


  	function validateChildKeys(node, parentType) {
  	  {
  	    if (typeof node !== 'object') {
  	      return;
  	    }

  	    if (isArray(node)) {
  	      for (var i = 0; i < node.length; i++) {
  	        var child = node[i];

  	        if (isValidElement(child)) {
  	          validateExplicitKey(child, parentType);
  	        }
  	      }
  	    } else if (isValidElement(node)) {
  	      // This element was passed in a valid location.
  	      if (node._store) {
  	        node._store.validated = true;
  	      }
  	    } else if (node) {
  	      var iteratorFn = getIteratorFn(node);

  	      if (typeof iteratorFn === 'function') {
  	        // Entry iterators used to provide implicit keys,
  	        // but now we print a separate warning for them later.
  	        if (iteratorFn !== node.entries) {
  	          var iterator = iteratorFn.call(node);
  	          var step;

  	          while (!(step = iterator.next()).done) {
  	            if (isValidElement(step.value)) {
  	              validateExplicitKey(step.value, parentType);
  	            }
  	          }
  	        }
  	      }
  	    }
  	  }
  	}
  	/**
  	 * Given an element, validate that its props follow the propTypes definition,
  	 * provided by the type.
  	 *
  	 * @param {ReactElement} element
  	 */


  	function validatePropTypes(element) {
  	  {
  	    var type = element.type;

  	    if (type === null || type === undefined || typeof type === 'string') {
  	      return;
  	    }

  	    var propTypes;

  	    if (typeof type === 'function') {
  	      propTypes = type.propTypes;
  	    } else if (typeof type === 'object' && (type.$$typeof === REACT_FORWARD_REF_TYPE || // Note: Memo only checks outer props here.
  	    // Inner props are checked in the reconciler.
  	    type.$$typeof === REACT_MEMO_TYPE)) {
  	      propTypes = type.propTypes;
  	    } else {
  	      return;
  	    }

  	    if (propTypes) {
  	      // Intentionally inside to avoid triggering lazy initializers:
  	      var name = getComponentNameFromType(type);
  	      checkPropTypes(propTypes, element.props, 'prop', name, element);
  	    } else if (type.PropTypes !== undefined && !propTypesMisspellWarningShown) {
  	      propTypesMisspellWarningShown = true; // Intentionally inside to avoid triggering lazy initializers:

  	      var _name = getComponentNameFromType(type);

  	      error('Component %s declared `PropTypes` instead of `propTypes`. Did you misspell the property assignment?', _name || 'Unknown');
  	    }

  	    if (typeof type.getDefaultProps === 'function' && !type.getDefaultProps.isReactClassApproved) {
  	      error('getDefaultProps is only used on classic React.createClass ' + 'definitions. Use a static property named `defaultProps` instead.');
  	    }
  	  }
  	}
  	/**
  	 * Given a fragment, validate that it can only be provided with fragment props
  	 * @param {ReactElement} fragment
  	 */


  	function validateFragmentProps(fragment) {
  	  {
  	    var keys = Object.keys(fragment.props);

  	    for (var i = 0; i < keys.length; i++) {
  	      var key = keys[i];

  	      if (key !== 'children' && key !== 'key') {
  	        setCurrentlyValidatingElement$1(fragment);

  	        error('Invalid prop `%s` supplied to `React.Fragment`. ' + 'React.Fragment can only have `key` and `children` props.', key);

  	        setCurrentlyValidatingElement$1(null);
  	        break;
  	      }
  	    }

  	    if (fragment.ref !== null) {
  	      setCurrentlyValidatingElement$1(fragment);

  	      error('Invalid attribute `ref` supplied to `React.Fragment`.');

  	      setCurrentlyValidatingElement$1(null);
  	    }
  	  }
  	}

  	var didWarnAboutKeySpread = {};
  	function jsxWithValidation(type, props, key, isStaticChildren, source, self) {
  	  {
  	    var validType = isValidElementType(type); // We warn in this case but don't throw. We expect the element creation to
  	    // succeed and there will likely be errors in render.

  	    if (!validType) {
  	      var info = '';

  	      if (type === undefined || typeof type === 'object' && type !== null && Object.keys(type).length === 0) {
  	        info += ' You likely forgot to export your component from the file ' + "it's defined in, or you might have mixed up default and named imports.";
  	      }

  	      var sourceInfo = getSourceInfoErrorAddendum(source);

  	      if (sourceInfo) {
  	        info += sourceInfo;
  	      } else {
  	        info += getDeclarationErrorAddendum();
  	      }

  	      var typeString;

  	      if (type === null) {
  	        typeString = 'null';
  	      } else if (isArray(type)) {
  	        typeString = 'array';
  	      } else if (type !== undefined && type.$$typeof === REACT_ELEMENT_TYPE) {
  	        typeString = "<" + (getComponentNameFromType(type.type) || 'Unknown') + " />";
  	        info = ' Did you accidentally export a JSX literal instead of a component?';
  	      } else {
  	        typeString = typeof type;
  	      }

  	      error('React.jsx: type is invalid -- expected a string (for ' + 'built-in components) or a class/function (for composite ' + 'components) but got: %s.%s', typeString, info);
  	    }

  	    var element = jsxDEV(type, props, key, source, self); // The result can be nullish if a mock or a custom function is used.
  	    // TODO: Drop this when these are no longer allowed as the type argument.

  	    if (element == null) {
  	      return element;
  	    } // Skip key warning if the type isn't valid since our key validation logic
  	    // doesn't expect a non-string/function type and can throw confusing errors.
  	    // We don't want exception behavior to differ between dev and prod.
  	    // (Rendering will throw with a helpful message and as soon as the type is
  	    // fixed, the key warnings will appear.)


  	    if (validType) {
  	      var children = props.children;

  	      if (children !== undefined) {
  	        if (isStaticChildren) {
  	          if (isArray(children)) {
  	            for (var i = 0; i < children.length; i++) {
  	              validateChildKeys(children[i], type);
  	            }

  	            if (Object.freeze) {
  	              Object.freeze(children);
  	            }
  	          } else {
  	            error('React.jsx: Static children should always be an array. ' + 'You are likely explicitly calling React.jsxs or React.jsxDEV. ' + 'Use the Babel transform instead.');
  	          }
  	        } else {
  	          validateChildKeys(children, type);
  	        }
  	      }
  	    }

  	    {
  	      if (hasOwnProperty.call(props, 'key')) {
  	        var componentName = getComponentNameFromType(type);
  	        var keys = Object.keys(props).filter(function (k) {
  	          return k !== 'key';
  	        });
  	        var beforeExample = keys.length > 0 ? '{key: someKey, ' + keys.join(': ..., ') + ': ...}' : '{key: someKey}';

  	        if (!didWarnAboutKeySpread[componentName + beforeExample]) {
  	          var afterExample = keys.length > 0 ? '{' + keys.join(': ..., ') + ': ...}' : '{}';

  	          error('A props object containing a "key" prop is being spread into JSX:\n' + '  let props = %s;\n' + '  <%s {...props} />\n' + 'React keys must be passed directly to JSX without using spread:\n' + '  let props = %s;\n' + '  <%s key={someKey} {...props} />', beforeExample, componentName, afterExample, componentName);

  	          didWarnAboutKeySpread[componentName + beforeExample] = true;
  	        }
  	      }
  	    }

  	    if (type === REACT_FRAGMENT_TYPE) {
  	      validateFragmentProps(element);
  	    } else {
  	      validatePropTypes(element);
  	    }

  	    return element;
  	  }
  	} // These two functions exist to still get child warnings in dev
  	// even with the prod transform. This means that jsxDEV is purely
  	// opt-in behavior for better messages but that we won't stop
  	// giving you warnings if you use production apis.

  	function jsxWithValidationStatic(type, props, key) {
  	  {
  	    return jsxWithValidation(type, props, key, true);
  	  }
  	}
  	function jsxWithValidationDynamic(type, props, key) {
  	  {
  	    return jsxWithValidation(type, props, key, false);
  	  }
  	}

  	var jsx =  jsxWithValidationDynamic ; // we may want to special case jsxs internally to take advantage of static children.
  	// for now we can ship identical prod functions

  	var jsxs =  jsxWithValidationStatic ;

  	reactJsxRuntime_development.Fragment = REACT_FRAGMENT_TYPE;
  	reactJsxRuntime_development.jsx = jsx;
  	reactJsxRuntime_development.jsxs = jsxs;
  	  })();
  	}
  	return reactJsxRuntime_development;
  }

  var hasRequiredJsxRuntime;

  function requireJsxRuntime () {
  	if (hasRequiredJsxRuntime) return jsxRuntime.exports;
  	hasRequiredJsxRuntime = 1;

  	{
  	  jsxRuntime.exports = requireReactJsxRuntime_development();
  	}
  	return jsxRuntime.exports;
  }

  var jsxRuntimeExports = requireJsxRuntime();

  var Button = function Button(props) {
    return /*#__PURE__*/jsxRuntimeExports.jsx("button", _objectSpread2(_objectSpread2({
      style: {
        border: '1px solid black',
        backgroundColor: '#e6fafa',
        padding: '.25rem',
        margin: '.1rem .2rem',
        fontSize: '12px',
        cursor: 'pointer'
      }
    }, props), {}, {
      children: props.children
    }));
  };

  var ConceptProver = function ConceptProver() {
    var dispatch = useDispatch();
    var _useState = ReactOriginal.useState(),
      _useState2 = _slicedToArray(_useState, 2),
      selectedLayoutItem = _useState2[0],
      setSelectedLayoutItem = _useState2[1];
    var _useState3 = ReactOriginal.useState(),
      _useState4 = _slicedToArray(_useState3, 2),
      selectedFromListComponentType = _useState4[0],
      setSelectedFromListComponentType = _useState4[1];

    // Get the entire layout model from the Redux store.
    var theLayout = useSelector(function (state) {
      var _state$layoutModel;
      return state === null || state === void 0 || (_state$layoutModel = state.layoutModel) === null || _state$layoutModel === void 0 || (_state$layoutModel = _state$layoutModel.present) === null || _state$layoutModel === void 0 ? void 0 : _state$layoutModel.layout;
    });

    // Get the available components list from the redux store.
    var availableComponents = useSelector(function (state) {
      var _state$componentAndLa;
      return state === null || state === void 0 || (_state$componentAndLa = state.componentAndLayoutApi) === null || _state$componentAndLa === void 0 || (_state$componentAndLa = _state$componentAndLa.queries['getComponents(undefined)']) === null || _state$componentAndLa === void 0 ? void 0 : _state$componentAndLa.data;
    });

    // Get the uuid of the selected component from the Redux store.
    var selectedComponent = useSelector(function (state) {
      return state.ui.selection.items[0];
    });
    var itemsInLayout = [];
    var _flatComponentsList = function flatComponentsList(components) {
      components.forEach(function (component) {
        itemsInLayout.push(component);
        component.slots.forEach(function (slot) {
          return _flatComponentsList(slot.components);
        });
      });
    };
    theLayout.forEach(function (region) {
      _flatComponentsList(region.components || []);
    });
    var node = drupalSettings.canvas.layoutUtils.findComponentByUuid(theLayout, selectedComponent);
    var _ref = node !== null && node !== void 0 && node.type ? node.type.split('@') : [],
      _ref2 = _slicedToArray(_ref, 1),
      selectedComponentType = _ref2[0];

    // Create a dropdown with every available component as options.
    var componentsSelect = function componentsSelect() {
      return /*#__PURE__*/jsxRuntimeExports.jsx("div", {
        children: /*#__PURE__*/jsxRuntimeExports.jsxs("label", {
          children: ["Components available in library:", /*#__PURE__*/jsxRuntimeExports.jsx("br", {}), /*#__PURE__*/jsxRuntimeExports.jsxs("select", {
            "data-testid": "ex-select-component",
            style: {
              maxWidth: '250px'
            },
            onChange: function onChange(e) {
              return setSelectedFromListComponentType(e.target.value);
            },
            children: [/*#__PURE__*/jsxRuntimeExports.jsx("option", {
              value: "",
              children: _typeof(availableComponents) === 'object' ? '--Select A Component--' : '-- Component List Not Ready --'
            }, 99999999), _typeof(availableComponents) === 'object' && Object.entries(availableComponents).map(function (_ref3, index) {
              var _ref4 = _slicedToArray(_ref3, 2);
                _ref4[0];
                var item = _ref4[1];
              return /*#__PURE__*/jsxRuntimeExports.jsx("option", {
                value: item.id,
                children: item.name
              }, index);
            })]
          }), selectedFromListComponentType && /*#__PURE__*/jsxRuntimeExports.jsx(Button, {
            "data-testid": "ex-insert",
            onClick: function onClick() {
              var _component$propSource;
              var component = availableComponents[selectedFromListComponentType];
              var withValues = component !== null && component !== void 0 && (_component$propSource = component.propSources) !== null && _component$propSource !== void 0 && _component$propSource.heading ? {
                heading: 'Hijacked Value'
              } : null;
              dispatch(drupalSettings.canvas.layoutUtils.addNewComponentToLayout({
                component: component,
                withValues: withValues
              }, drupalSettings.canvas.componentSelectionUtils.setSelectedComponent));
            },
            children: "insert"
          })]
        })
      });
    };
    var layoutItemsSelect = function layoutItemsSelect() {
      return /*#__PURE__*/jsxRuntimeExports.jsxs("div", {
        children: [/*#__PURE__*/jsxRuntimeExports.jsxs("label", {
          children: ["Items in layout:", /*#__PURE__*/jsxRuntimeExports.jsx("br", {}), /*#__PURE__*/jsxRuntimeExports.jsxs("select", {
            "data-testid": "ex-select-in-layout",
            style: {
              maxWidth: '250px'
            },
            onChange: function onChange(e) {
              return setSelectedLayoutItem(e.target.value);
            },
            children: [/*#__PURE__*/jsxRuntimeExports.jsx("option", {
              value: "",
              children: itemsInLayout.length ? '--Choose an item in the layout--' : '-- No items in layout yet --'
            }, 99999999), itemsInLayout.map(function (item, index) {
              return /*#__PURE__*/jsxRuntimeExports.jsxs("option", {
                value: item.uuid,
                children: [item.type, "(", item.uuid, ")"]
              }, index);
            })]
          })]
        }), selectedLayoutItem && /*#__PURE__*/jsxRuntimeExports.jsx(Button, {
          "data-testid": "ex-focus",
          onClick: function onClick() {
            // Dispatch based on action name.
            // Update redux store so the layout item chosen is selected in the UI.
            drupalSettings.canvas.componentSelectionUtils.setSelectedComponent(selectedLayoutItem);
          },
          children: "focus"
        }), selectedLayoutItem && /*#__PURE__*/jsxRuntimeExports.jsx(Button, {
          "data-testid": "ex-delete",
          onClick: function onClick() {
            // Dispatch based on action name.
            // Update redux store so the layout item chosen is selected in the UI.
            dispatch({
              type: 'layoutModel/deleteNode',
              payload: selectedLayoutItem
            });
            // This sets the selected component to null so the contextual menu
            // closes instead of attempting to render to form for a deleted
            // component.
            dispatch({
              type: 'ui/unsetSelectedComponent'
            });
            setSelectedLayoutItem(null);
          },
          children: "delete"
        })]
      });
    };
    return /*#__PURE__*/jsxRuntimeExports.jsx("div", {
      style: {
        backgroundColor: '#c0ffee',
        border: '1px solid #ccc',
        bottom: '2rem',
        padding: '.75rem'
      },
      children: /*#__PURE__*/jsxRuntimeExports.jsxs("div", {
        children: [layoutItemsSelect(), componentsSelect(), /*#__PURE__*/jsxRuntimeExports.jsxs("div", {
          style: {
            marginTop: '1rem'
          },
          children: [/*#__PURE__*/jsxRuntimeExports.jsx("b", {
            children: "Event: Detect selected element"
          }), ":", /*#__PURE__*/jsxRuntimeExports.jsx("br", {}), /*#__PURE__*/jsxRuntimeExports.jsx("small", {
            "data-testid": "ex-selected-element",
            children: selectedComponent
          })]
        }), selectedComponentType === 'sdc.canvas_test_sdc.my-hero' && /*#__PURE__*/jsxRuntimeExports.jsx(Button, {
          "data-testid": "ex-update",
          onClick: function onClick() {
            dispatch(drupalSettings.canvas.layoutUtils.updateExistingComponentValues({
              componentToUpdateId: selectedComponent,
              values: {
                heading: 'an extension updated this'
              }
            }));
          },
          children: "Update the heading value of the selected hero component"
        })]
      })
    });
  };

  var EXTENSION_ID = 'canvas-test-extension';
  var _window$1 = window,
    drupalSettings$2 = _window$1.drupalSettings;
  drupalSettings$2.canvasExtension.testExtension.component = ConceptProver;
  var ExampleExtension = function ExampleExtension() {
    var _useState = ReactOriginal.useState(null),
      _useState2 = _slicedToArray(_useState, 2),
      portalRoot = _useState2[0],
      setPortalRoot = _useState2[1];

    // Get the currently active extension from the Canvas React app's Redux store.
    var activeExtension = useSelector(function (state) {
      return state.extensions.activeExtension;
    });
    ReactOriginal.useEffect(function () {
      if (activeExtension !== null && activeExtension !== void 0 && activeExtension.id) {
        // Wait for a tick here to ensure the div in the extension modal has been rendered so we can portal
        // our extension into it.
        requestAnimationFrame(function () {
          var targetDiv = document.querySelector("#extensionPortalContainer.canvas-extension-".concat(activeExtension.id));
          if (targetDiv) {
            setPortalRoot(targetDiv);
          }
        });
      }
    }, [activeExtension]);

    // We don't want to render anything if the Extension is not active in the Canvas app.
    if ((activeExtension === null || activeExtension === void 0 ? void 0 : activeExtension.id) !== EXTENSION_ID || !portalRoot) {
      return null;
    }

    // This step isn't really necessary in this file, but it demonstrates we can
    // add the entry point component to drupalSettings, which should make it
    // possible to eventually manage most of this in the UI app, with the
    // extension still adding the component to drupalSettings.
    var ExtensionComponent = drupalSettings$2.canvasExtension.testExtension.component;
    return /*#__PURE__*/require$$0__default["default"].createPortal(/*#__PURE__*/jsxRuntimeExports.jsx(ExtensionComponent, {}), portalRoot);
  };

  var _window = window,
    drupalSettings$1 = _window.drupalSettings;
  var container = document.createElement('div');
  container.id = 'canvas-test-extension';
  document.body.append(container);
  var root = clientExports.createRoot(container);

  // The Canvas store is available in Drupal settings, making it possible to add it
  // to this App via a <Provider>, giving us access to its data and actions.
  var store = drupalSettings$1.canvas.store;
  root.render(/*#__PURE__*/jsxRuntimeExports.jsx(Provider_default, {
    store: store,
    children: /*#__PURE__*/jsxRuntimeExports.jsx(ExampleExtension, {})
  }));

}));
