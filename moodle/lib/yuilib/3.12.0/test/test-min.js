/*
YUI 3.12.0 (build 8655935)
Copyright 2013 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/

YUI.add("test",function(e,t){YUI.YUITest?e.Test=YUI.YUITest:(YUITest={version:"3.12.0",guid:function(t){return e.guid(t)}},e.namespace("Test"),YUITest.Object=e.Object,YUITest.Array=e.Array,YUITest.Util={mix:e.mix,JSON:e.JSON},YUITest.EventTarget=function(){this._handlers={}},YUITest.EventTarget.prototype={constructor:YUITest.EventTarget,attach:function(e,t){typeof this._handlers[e]=="undefined"&&(this._handlers[e]=[]),this._handlers[e].push(t)},subscribe:function(e,t){this.attach.apply(this,arguments)},fire:function(e){typeof e=="string"&&(e={type:e}),e.target||(e.target=this);if(!e.type)throw new Error("Event object missing 'type' property.");if(this._handlers[e.type]instanceof Array){var t=this._handlers[e.type];for(var n=0,r=t.length;n<r;n++)t[n].call(this,e)}},detach:function(e,t){if(this._handlers[e]instanceof Array){var n=this._handlers[e];for(var r=0,i=n.length;r<i;r++)if(n[r]===t){n.splice(r,1);break}}},unsubscribe:function(e,t){this.detach.apply(this,arguments)}},YUITest.TestSuite=function(e){this.name="",this.items=[];if(typeof e=="string")this.name=e;else if(e instanceof Object)for(var t in e)e.hasOwnProperty(t)&&(this[t]=e[t]);if(this.name===""||!this.name)this.name=YUITest.guid("testSuite_")},YUITest.TestSuite.prototype={constructor:YUITest.TestSuite,add:function(e){return(e instanceof YUITest.TestSuite||e instanceof YUITest.TestCase)&&this.items.push(e),this},setUp:function(){},tearDown:function(){}},YUITest.TestCase=function(e){this._should={};for(var t in e)this[t]=e[t];typeof this.name!="string"&&(this.name=YUITest.guid("testCase_"))},YUITest.TestCase.prototype={constructor:YUITest.TestCase,callback:function(){return YUITest.TestRunner.callback.apply(YUITest.TestRunner,arguments)},resume:function(e){YUITest.TestRunner.resume(e)},wait:function(e,t){var n=typeof e=="number"?e:t;throw n=typeof n=="number"?n:1e4,typeof e=="function"?new YUITest.Wait(e,n):new YUITest.Wait(function(){YUITest.Assert.fail("Timeout: wait() called but resume() never called.")},n)},assert:function(e,t){YUITest.Assert._increment();if(!e)throw new YUITest.AssertionError(YUITest.Assert._formatMessage(t,"Assertion failed."))},fail:function(e){YUITest.Assert.fail(e)},init:function(){},destroy:function(){},setUp:function(){},tearDown:function(){}},YUITest.TestFormat=function(){function e(e){return e.replace(/[<>"'&]/g,function(e){switch(e){case"<":return"&lt;";case">":return"&gt;";case'"':return"&quot;";case"'":return"&apos;";case"&":return"&amp;"}})}return{JSON:function(e){return YUITest.Util.JSON.stringify(e)},XML:function(t){function n(t){var r="<"+t.type+' name="'+e(t.name)+'"';typeof t.duration=="number"&&(r+=' duration="'+t.duration+'"');if(t.type=="test")r+=' result="'+t.result+'" message="'+e(t.message)+'">';else{r+=' passed="'+t.passed+'" failed="'+t.failed+'" ignored="'+t.ignored+'" total="'+t.total+'">';for(var i in t)t.hasOwnProperty(i)&&t[i]&&typeof t[i]=="object"&&!(t[i]instanceof Array)&&(r+=n(t[i]))}return r+="</"+t.type+">",r}return'<?xml version="1.0" encoding="UTF-8"?>'+n(t)},JUnitXML:function(t){function n(t){var r="";switch(t.type){case"test":t.result!="ignore"&&(r='<testcase name="'+e(t.name)+'" time="'+t.duration/1e3+'">',t.result=="fail"&&(r+='<failure message="'+e(t.message)+'"><![CDATA['+t.message+"]]></failure>"),r+="</testcase>");break;case"testcase":r='<testsuite name="'+e(t.name)+'" tests="'+t.total+'" failures="'+t.failed+'" time="'+t.duration/1e3+'">';for(var i in t)t.hasOwnProperty(i)&&t[i]&&typeof t[i]=="object"&&!(t[i]instanceof Array)&&(r+=n(t[i]));r+="</testsuite>";break;case"testsuite":for(var i in t)t.hasOwnProperty(i)&&t[i]&&typeof t[i]=="object"&&!(t[i]instanceof Array)&&(r+=n(t[i]));break;case"report":r="<testsuites>";for(var i in t)t.hasOwnProperty(i)&&t[i]&&typeof t[i]=="object"&&!(t[i]instanceof Array)&&(r+=n(t[i]));r+="</testsuites>"}return r}return'<?xml version="1.0" encoding="UTF-8"?>'+n(t)},TAP:function(e){function n(e){var r="";switch(e.type){case"test":e.result!="ignore"?(r="ok "+t++ +" - "+e.name,e.result=="fail"&&(r="not "+r+" - "+e.message),r+="\n"):r="#Ignored test "+e.name+"\n";break;case"testcase":r="#Begin testcase "+e.name+"("+e.failed+" failed of "+e.total+")\n";for(var i in e)e.hasOwnProperty(i)&&e[i]&&typeof e[i]=="object"&&!(e[i]instanceof Array)&&(r+=n(e[i]));r+="#End testcase "+e.name+"\n";break;case"testsuite":r="#Begin testsuite "+e.name+"("+e.failed+" failed of "+e.total+")\n";for(var i in e)e.hasOwnProperty(i)&&e[i]&&typeof e[i]=="object"&&!(e[i]instanceof Array)&&(r+=n(e[i]));r+="#End testsuite "+e.name+"\n";break;case"report":for(var i in e)e.hasOwnProperty(i)&&e[i]&&typeof e[i]=="object"&&!(e[i]instanceof Array)&&(r+=n(e[i]))}return r}var t=1;return"1.."+e.total+"\n"+n(e)}}}(),YUITest.Reporter=function(e,t){this.url=e,this.format=t||YUITest.TestFormat.XML,this._fields=new Object,this._form=null,this._iframe=null},YUITest.Reporter.prototype={constructor:YUITest.Reporter,addField:function(e,t){this._fields[e]=t},clearFields:function(){this._fields=new Object},destroy:function(){this._form&&(this._form.parentNode.removeChild(this._form),this._form=null),this._iframe&&(this._iframe.parentNode.removeChild(this._iframe),this._iframe=null),this._fields=null},report:function(e){if(!this._form){this._form=document.createElement("form"),this._form.method="post",this._form.style.visibility="hidden",this._form.style.position="absolute",this._form.style.top=0,document.body.appendChild(this._form);try{this._iframe=document.createElement('<iframe name="yuiTestTarget" />')}catch(t){this._iframe=document.createElement("iframe"),this._iframe.name="yuiTestTarget"}this._iframe.src="javascript:false",this._iframe.style.visibility="hidden",this._iframe.style.position="absolute",this._iframe.style.top=0,document.body.appendChild(this._iframe),this._form.target="yuiTestTarget"}this._form.action=this.url;while(this._form.hasChildNodes())this._form.removeChild(this._form.lastChild);this._fields.results=this.format(e),this._fields.useragent=navigator.userAgent
,this._fields.timestamp=(new Date).toLocaleString();for(var n in this._fields){var r=this._fields[n];if(this._fields.hasOwnProperty(n)&&typeof r!="function"){var i=document.createElement("input");i.type="hidden",i.name=n,i.value=r,this._form.appendChild(i)}}delete this._fields.results,delete this._fields.useragent,delete this._fields.timestamp,arguments[1]!==!1&&this._form.submit()}},YUITest.TestRunner=function(){function e(e,t){if(!t.length)return!0;if(e)for(var n=0,r=e.length;n<r;n++)if(t.indexOf(","+e[n]+",")>-1)return!0;return!1}function t(e){this.testObject=e,this.firstChild=null,this.lastChild=null,this.parent=null,this.next=null,this.results=new YUITest.Results,e instanceof YUITest.TestSuite?(this.results.type="testsuite",this.results.name=e.name):e instanceof YUITest.TestCase&&(this.results.type="testcase",this.results.name=e.name)}function n(){YUITest.EventTarget.call(this),this.masterSuite=new YUITest.TestSuite(YUITest.guid("testSuite_")),this._cur=null,this._root=null,this._log=!0,this._waiting=!1,this._running=!1,this._lastResults=null,this._context=null,this._groups=""}return t.prototype={appendChild:function(e){var n=new t(e);return this.firstChild===null?this.firstChild=this.lastChild=n:(this.lastChild.next=n,this.lastChild=n),n.parent=this,n}},n.prototype=YUITest.Util.mix(new YUITest.EventTarget,{_ignoreEmpty:!1,constructor:YUITest.TestRunner,TEST_CASE_BEGIN_EVENT:"testcasebegin",TEST_CASE_COMPLETE_EVENT:"testcasecomplete",TEST_SUITE_BEGIN_EVENT:"testsuitebegin",TEST_SUITE_COMPLETE_EVENT:"testsuitecomplete",TEST_PASS_EVENT:"pass",TEST_FAIL_EVENT:"fail",ERROR_EVENT:"error",TEST_IGNORE_EVENT:"ignore",COMPLETE_EVENT:"complete",BEGIN_EVENT:"begin",_addTestCaseToTestTree:function(e,t){var n=e.appendChild(t),r,i;for(r in t)(r.indexOf("test")===0||r.indexOf(" ")>-1)&&typeof t[r]=="function"&&n.appendChild(r)},_addTestSuiteToTestTree:function(e,t){var n=e.appendChild(t);for(var r=0;r<t.items.length;r++)t.items[r]instanceof YUITest.TestSuite?this._addTestSuiteToTestTree(n,t.items[r]):t.items[r]instanceof YUITest.TestCase&&this._addTestCaseToTestTree(n,t.items[r])},_buildTestTree:function(){this._root=new t(this.masterSuite);for(var e=0;e<this.masterSuite.items.length;e++)this.masterSuite.items[e]instanceof YUITest.TestSuite?this._addTestSuiteToTestTree(this._root,this.masterSuite.items[e]):this.masterSuite.items[e]instanceof YUITest.TestCase&&this._addTestCaseToTestTree(this._root,this.masterSuite.items[e])},_handleTestObjectComplete:function(e){var t;e&&typeof e.testObject=="object"&&(t=e.parent,t&&(t.results.include(e.results),t.results[e.testObject.name]=e.results),e.testObject instanceof YUITest.TestSuite?(this._execNonTestMethod(e,"tearDown",!1),e.results.duration=new Date-e._start,this.fire({type:this.TEST_SUITE_COMPLETE_EVENT,testSuite:e.testObject,results:e.results})):e.testObject instanceof YUITest.TestCase&&(this._execNonTestMethod(e,"destroy",!1),e.results.duration=new Date-e._start,this.fire({type:this.TEST_CASE_COMPLETE_EVENT,testCase:e.testObject,results:e.results})))},_next:function(){if(this._cur===null)this._cur=this._root;else if(this._cur.firstChild)this._cur=this._cur.firstChild;else if(this._cur.next)this._cur=this._cur.next;else{while(this._cur&&!this._cur.next&&this._cur!==this._root)this._handleTestObjectComplete(this._cur),this._cur=this._cur.parent;this._handleTestObjectComplete(this._cur),this._cur==this._root?(this._cur.results.type="report",this._cur.results.timestamp=(new Date).toLocaleString(),this._cur.results.duration=new Date-this._cur._start,this._lastResults=this._cur.results,this._running=!1,this.fire({type:this.COMPLETE_EVENT,results:this._lastResults}),this._cur=null):this._cur&&(this._cur=this._cur.next)}return this._cur},_execNonTestMethod:function(e,t,n){var r=e.testObject,i={type:this.ERROR_EVENT};try{if(n&&r["async:"+t])return r["async:"+t](this._context),!0;r[t](this._context)}catch(s){e.results.errors++,i.error=s,i.methodName=t,r instanceof YUITest.TestCase?i.testCase=r:i.testSuite=testSuite,this.fire(i)}return!1},_run:function(){var e=!1,t=this._next();if(t!==null){this._running=!0,this._lastResult=null;var n=t.testObject;if(typeof n=="object"&&n!==null){if(n instanceof YUITest.TestSuite)this.fire({type:this.TEST_SUITE_BEGIN_EVENT,testSuite:n}),t._start=new Date,this._execNonTestMethod(t,"setUp",!1);else if(n instanceof YUITest.TestCase){this.fire({type:this.TEST_CASE_BEGIN_EVENT,testCase:n}),t._start=new Date;if(this._execNonTestMethod(t,"init",!0))return}typeof setTimeout!="undefined"?setTimeout(function(){YUITest.TestRunner._run()},0):this._run()}else this._runTest(t)}},_resumeTest:function(e){var t=this._cur;this._waiting=!1;if(!t)return;var n=t.testObject,r=t.parent.testObject;r.__yui_wait&&(clearTimeout(r.__yui_wait),delete r.__yui_wait);var i=n.indexOf("fail:")===0||(r._should.fail||{})[n],s=(r._should.error||{})[n],o=!1,u=null;try{e.call(r,this._context);if(YUITest.Assert._getCount()==0&&!this._ignoreEmpty)throw new YUITest.AssertionError("Test has no asserts.");i?(u=new YUITest.ShouldFail,o=!0):s&&(u=new YUITest.ShouldError,o=!0)}catch(a){r.__yui_wait&&(clearTimeout(r.__yui_wait),delete r.__yui_wait);if(a instanceof YUITest.AssertionError)i||(u=a,o=!0);else{if(a instanceof YUITest.Wait){if(typeof a.segment=="function"&&typeof a.delay=="number"){if(typeof setTimeout=="undefined")throw new Error("Asynchronous tests not supported in this environment.");r.__yui_wait=setTimeout(function(){YUITest.TestRunner._resumeTest(a.segment)},a.delay),this._waiting=!0}return}s?typeof s=="string"?a.message!=s&&(u=new YUITest.UnexpectedError(a),o=!0):typeof s=="function"?a instanceof s||(u=new YUITest.UnexpectedError(a),o=!0):typeof s=="object"&&s!==null&&(!(a instanceof s.constructor)||a.message!=s.message)&&(u=new YUITest.UnexpectedError(a),o=!0):(u=new YUITest.UnexpectedError(a),o=!0)}}o?this.fire({type:this.TEST_FAIL_EVENT,testCase:r,testName:n,error:u}):this.fire({type:this.TEST_PASS_EVENT,testCase:r,testName:n}),this._execNonTestMethod(t.parent
,"tearDown",!1),YUITest.Assert._reset();var f=new Date-t._start;t.parent.results[n]={result:o?"fail":"pass",message:u?u.getMessage():"Test passed",type:"test",name:n,duration:f},o?t.parent.results.failed++:t.parent.results.passed++,t.parent.results.total++,typeof setTimeout!="undefined"?setTimeout(function(){YUITest.TestRunner._run()},0):this._run()},_handleError:function(e){if(!this._waiting)throw e;this._resumeTest(function(){throw e})},_runTest:function(t){var n=t.testObject,r=t.parent.testObject,i=r[n],s=n.indexOf("ignore:")===0||!e(r.groups,this._groups)||(r._should.ignore||{})[n];s?(t.parent.results[n]={result:"ignore",message:"Test ignored",type:"test",name:n.indexOf("ignore:")===0?n.substring(7):n},t.parent.results.ignored++,t.parent.results.total++,this.fire({type:this.TEST_IGNORE_EVENT,testCase:r,testName:n}),typeof setTimeout!="undefined"?setTimeout(function(){YUITest.TestRunner._run()},0):this._run()):(t._start=new Date,this._execNonTestMethod(t.parent,"setUp",!1),this._resumeTest(i))},getName:function(){return this.masterSuite.name},setName:function(e){this.masterSuite.name=e},add:function(e){return this.masterSuite.add(e),this},clear:function(){this.masterSuite=new YUITest.TestSuite(YUITest.guid("testSuite_"))},isWaiting:function(){return this._waiting},isRunning:function(){return this._running},getResults:function(e){return!this._running&&this._lastResults?typeof e=="function"?e(this._lastResults):this._lastResults:null},getCoverage:function(e){var t=null;return typeof _yuitest_coverage=="object"&&(t=_yuitest_coverage),typeof __coverage__=="object"&&(t=__coverage__),!this._running&&typeof t=="object"?typeof e=="function"?e(t):t:null},callback:function(){var e=arguments,t=this._context,n=this;return function(){for(var r=0;r<arguments.length;r++)t[e[r]]=arguments[r];n._run()}},resume:function(e){if(!this._waiting)throw new Error("resume() called without wait().");this._resumeTest(e||function(){})},run:function(e){e=e||{};var t=YUITest.TestRunner,n=e.oldMode;!n&&this.masterSuite.items.length==1&&this.masterSuite.items[0]instanceof YUITest.TestSuite&&(this.masterSuite=this.masterSuite.items[0]),t._groups=e.groups instanceof Array?","+e.groups.join(",")+",":"",t._buildTestTree(),t._context={},t._root._start=new Date,t.fire(t.BEGIN_EVENT),t._run()}}),new n}(),YUITest.ArrayAssert={_indexOf:function(e,t){if(e.indexOf)return e.indexOf(t);for(var n=0;n<e.length;n++)if(e[n]===t)return n;return-1},_some:function(e,t){if(e.some)return e.some(t);for(var n=0;n<e.length;n++)if(t(e[n]))return!0;return!1},contains:function(e,t,n){YUITest.Assert._increment(),this._indexOf(t,e)==-1&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value "+e+" ("+typeof e+") not found in array ["+t+"]."))},containsItems:function(e,t,n){YUITest.Assert._increment();for(var r=0;r<e.length;r++)this._indexOf(t,e[r])==-1&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value "+e[r]+" ("+typeof e[r]+") not found in array ["+t+"]."))},containsMatch:function(e,t,n){YUITest.Assert._increment();if(typeof e!="function")throw new TypeError("ArrayAssert.containsMatch(): First argument must be a function.");this._some(t,e)||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"No match found in array ["+t+"]."))},doesNotContain:function(e,t,n){YUITest.Assert._increment(),this._indexOf(t,e)>-1&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value found in array ["+t+"]."))},doesNotContainItems:function(e,t,n){YUITest.Assert._increment();for(var r=0;r<e.length;r++)this._indexOf(t,e[r])>-1&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value found in array ["+t+"]."))},doesNotContainMatch:function(e,t,n){YUITest.Assert._increment();if(typeof e!="function")throw new TypeError("ArrayAssert.doesNotContainMatch(): First argument must be a function.");this._some(t,e)&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value found in array ["+t+"]."))},indexOf:function(e,t,n,r){YUITest.Assert._increment();for(var i=0;i<t.length;i++)if(t[i]===e){n!=i&&YUITest.Assert.fail(YUITest.Assert._formatMessage(r,"Value exists at index "+i+" but should be at index "+n+"."));return}YUITest.Assert.fail(YUITest.Assert._formatMessage(r,"Value doesn't exist in array ["+t+"]."))},itemsAreEqual:function(e,t,n){YUITest.Assert._increment(),(typeof e!="object"||typeof t!="object")&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Value should be an array.")),e.length!=t.length&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Array should have a length of "+e.length+" but has a length of "+t.length+"."));for(var r=0;r<e.length;r++)if(e[r]!=t[r])throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Values in position "+r+" are not equal."),e[r],t[r])},itemsAreEquivalent:function(e,t,n,r){YUITest.Assert._increment();if(typeof n!="function")throw new TypeError("ArrayAssert.itemsAreEquivalent(): Third argument must be a function.");e.length!=t.length&&YUITest.Assert.fail(YUITest.Assert._formatMessage(r,"Array should have a length of "+e.length+" but has a length of "+t.length));for(var i=0;i<e.length;i++)if(!n(e[i],t[i]))throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(r,"Values in position "+i+" are not equivalent."),e[i],t[i])},isEmpty:function(e,t){YUITest.Assert._increment(),e.length>0&&YUITest.Assert.fail(YUITest.Assert._formatMessage(t,"Array should be empty."))},isNotEmpty:function(e,t){YUITest.Assert._increment(),e.length===0&&YUITest.Assert.fail(YUITest.Assert._formatMessage(t,"Array should not be empty."))},itemsAreSame:function(e,t,n){YUITest.Assert._increment(),e.length!=t.length&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Array should have a length of "+e.length+" but has a length of "+t.length));for(var r=0;r<e.length;r++)if(e[r]!==t[r])throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Values in position "+r+" are not the same."),e[r],t[r])},lastIndexOf:function(e,t,n,r){for(var i=t.length;i>=0;i--)if(t[i]===e){n!=i&&YUITest.Assert.fail(YUITest.Assert._formatMessage
(r,"Value exists at index "+i+" but should be at index "+n+"."));return}YUITest.Assert.fail(YUITest.Assert._formatMessage(r,"Value doesn't exist in array."))}},YUITest.Assert={_asserts:0,_formatMessage:function(e,t){return typeof e=="string"&&e.length>0?e.replace("{message}",t):t},_getCount:function(){return this._asserts},_increment:function(){this._asserts++},_reset:function(){this._asserts=0},fail:function(e){throw new YUITest.AssertionError(YUITest.Assert._formatMessage(e,"Test force-failed."))},pass:function(e){YUITest.Assert._increment()},areEqual:function(e,t,n){YUITest.Assert._increment();if(e!=t)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Values should be equal."),e,t)},areNotEqual:function(e,t,n){YUITest.Assert._increment();if(e==t)throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(n,"Values should not be equal."),e)},areNotSame:function(e,t,n){YUITest.Assert._increment();if(e===t)throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(n,"Values should not be the same."),e)},areSame:function(e,t,n){YUITest.Assert._increment();if(e!==t)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Values should be the same."),e,t)},isFalse:function(e,t){YUITest.Assert._increment();if(!1!==e)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(t,"Value should be false."),!1,e)},isTrue:function(e,t){YUITest.Assert._increment();if(!0!==e)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(t,"Value should be true."),!0,e)},isNaN:function(e,t){YUITest.Assert._increment();if(!isNaN(e))throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(t,"Value should be NaN."),NaN,e)},isNotNaN:function(e,t){YUITest.Assert._increment();if(isNaN(e))throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Values should not be NaN."),NaN)},isNotNull:function(e,t){YUITest.Assert._increment();if(e===null)throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Values should not be null."),null)},isNotUndefined:function(e,t){YUITest.Assert._increment();if(typeof e=="undefined")throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should not be undefined."),undefined)},isNull:function(e,t){YUITest.Assert._increment();if(e!==null)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(t,"Value should be null."),null,e)},isUndefined:function(e,t){YUITest.Assert._increment();if(typeof e!="undefined")throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(t,"Value should be undefined."),undefined,e)},isArray:function(e,t){YUITest.Assert._increment();var n=!1;Array.isArray?n=!Array.isArray(e):n=Object.prototype.toString.call(e)!="[object Array]";if(n)throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be an array."),e)},isBoolean:function(e,t){YUITest.Assert._increment();if(typeof e!="boolean")throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be a Boolean."),e)},isFunction:function(e,t){YUITest.Assert._increment();if(!(e instanceof Function))throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be a function."),e)},isInstanceOf:function(e,t,n){YUITest.Assert._increment();if(!(t instanceof e))throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Value isn't an instance of expected type."),e,t)},isNumber:function(e,t){YUITest.Assert._increment();if(typeof e!="number")throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be a number."),e)},isObject:function(e,t){YUITest.Assert._increment();if(!e||typeof e!="object"&&typeof e!="function")throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be an object."),e)},isString:function(e,t){YUITest.Assert._increment();if(typeof e!="string")throw new YUITest.UnexpectedValue(YUITest.Assert._formatMessage(t,"Value should be a string."),e)},isTypeOf:function(e,t,n){YUITest.Assert._increment();if(typeof t!=e)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Value should be of type "+e+"."),e,typeof t)},throwsError:function(e,t,n){YUITest.Assert._increment();var r=!1;try{t()}catch(i){if(typeof e=="string")i.message!=e&&(r=!0);else if(typeof e=="function")i instanceof e||(r=!0);else if(typeof e=="object"&&e!==null){if(!(i instanceof e.constructor)||i.message!=e.message)r=!0}else r=!0;if(r)throw new YUITest.UnexpectedError(i);return}throw new YUITest.AssertionError(YUITest.Assert._formatMessage(n,"Error should have been thrown."))}},YUITest.AssertionError=function(e){this.message=e,this.name="Assert Error"},YUITest.AssertionError.prototype={constructor:YUITest.AssertionError,getMessage:function(){return this.message},toString:function(){return this.name+": "+this.getMessage()}},YUITest.ComparisonFailure=function(e,t,n){YUITest.AssertionError.call(this,e),this.expected=t,this.actual=n,this.name="ComparisonFailure"},YUITest.ComparisonFailure.prototype=new YUITest.AssertionError,YUITest.ComparisonFailure.prototype.constructor=YUITest.ComparisonFailure,YUITest.ComparisonFailure.prototype.getMessage=function(){return this.message+"\nExpected: "+this.expected+" ("+typeof this.expected+")"+"\nActual: "+this.actual+" ("+typeof this.actual+")"},YUITest.CoverageFormat={JSON:function(e){return YUITest.Util.JSON.stringify(e)},XdebugJSON:function(e){var t={};for(var n in e)e.hasOwnProperty(n)&&(t[n]=e[n].lines);return YUITest.Util.JSON.stringify(e)}},YUITest.DateAssert={datesAreEqual:function(e,t,n){YUITest.Assert._increment();if(!(e instanceof Date&&t instanceof Date))throw new TypeError("YUITest.DateAssert.datesAreEqual(): Expected and actual values must be Date objects.");var r="";e.getFullYear()!=t.getFullYear()&&(r="Years should be equal."),e.getMonth()!=t.getMonth()&&(r="Months should be equal."),e.getDate()!=t.getDate()&&(r="Days of month should be equal.");if(r.length)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,r),e,t)},timesAreEqual:function(e,t,n)
{YUITest.Assert._increment();if(!(e instanceof Date&&t instanceof Date))throw new TypeError("YUITest.DateAssert.timesAreEqual(): Expected and actual values must be Date objects.");var r="";e.getHours()!=t.getHours()&&(r="Hours should be equal."),e.getMinutes()!=t.getMinutes()&&(r="Minutes should be equal."),e.getSeconds()!=t.getSeconds()&&(r="Seconds should be equal.");if(r.length)throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,r),e,t)}},YUITest.Mock=function(e){e=e||{};var t,n;try{function r(){}r.prototype=e,t=new r}catch(i){t={}}for(n in e)e.hasOwnProperty(n)&&typeof e[n]=="function"&&(t[n]=function(e){return function(){YUITest.Assert.fail("Method "+e+"() was called but was not expected to be.")}}(n));return t},YUITest.Mock.expect=function(e,t){e.__expectations||(e.__expectations={});if(t.method){var n=t.method,r=t.args||[],i=t.returns,s=typeof t.callCount=="number"?t.callCount:1,o=t.error,u=t.run||function(){},a,f;e.__expectations[n]=t,t.callCount=s,t.actualCallCount=0;for(f=0;f<r.length;f++)r[f]instanceof YUITest.Mock.Value||(r[f]=YUITest.Mock.Value(YUITest.Assert.areSame,[r[f]],"Argument "+f+" of "+n+"() is incorrect."));s>0?e[n]=function(){try{t.actualCallCount++,YUITest.Assert.areEqual(r.length,arguments.length,"Method "+n+"() passed incorrect number of arguments.");for(var e=0,s=r.length;e<s;e++)r[e].verify(arguments[e]);a=u.apply(this,arguments);if(o)throw o}catch(f){YUITest.TestRunner._handleError(f)}return t.hasOwnProperty("returns")?i:a}:e[n]=function(){try{YUITest.Assert.fail("Method "+n+"() should not have been called.")}catch(e){YUITest.TestRunner._handleError(e)}}}else t.property&&(e.__expectations[t.property]=t)},YUITest.Mock.verify=function(e){try{for(var t in e.__expectations)if(e.__expectations.hasOwnProperty(t)){var n=e.__expectations[t];n.method?YUITest.Assert.areEqual(n.callCount,n.actualCallCount,"Method "+n.method+"() wasn't called the expected number of times."):n.property&&YUITest.Assert.areEqual(n.value,e[n.property],"Property "+n.property+" wasn't set to the correct value.")}}catch(r){YUITest.TestRunner._handleError(r)}},YUITest.Mock.Value=function(e,t,n){if(!(this instanceof YUITest.Mock.Value))return new YUITest.Mock.Value(e,t,n);this.verify=function(r){var i=[].concat(t||[]);i.push(r),i.push(n),e.apply(null,i)}},YUITest.Mock.Value.Any=YUITest.Mock.Value(function(){}),YUITest.Mock.Value.Boolean=YUITest.Mock.Value(YUITest.Assert.isBoolean),YUITest.Mock.Value.Number=YUITest.Mock.Value(YUITest.Assert.isNumber),YUITest.Mock.Value.String=YUITest.Mock.Value(YUITest.Assert.isString),YUITest.Mock.Value.Object=YUITest.Mock.Value(YUITest.Assert.isObject),YUITest.Mock.Value.Function=YUITest.Mock.Value(YUITest.Assert.isFunction),YUITest.ObjectAssert={areEqual:function(e,t,n){YUITest.Assert._increment();var r=YUITest.Object.keys(e),i=YUITest.Object.keys(t);r.length!=i.length&&YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Object should have "+r.length+" keys but has "+i.length));for(var s in e)if(e.hasOwnProperty(s)&&e[s]!=t[s])throw new YUITest.ComparisonFailure(YUITest.Assert._formatMessage(n,"Values should be equal for property "+s),e[s],t[s])},hasKey:function(e,t,n){YUITest.ObjectAssert.ownsOrInheritsKey(e,t,n)},hasKeys:function(e,t,n){YUITest.ObjectAssert.ownsOrInheritsKeys(e,t,n)},inheritsKey:function(e,t,n){YUITest.Assert._increment(),e in t&&!t.hasOwnProperty(e)||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e+"' not found on object instance."))},inheritsKeys:function(e,t,n){YUITest.Assert._increment();for(var r=0;r<e.length;r++)propertyName in t&&!t.hasOwnProperty(e[r])||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e[r]+"' not found on object instance."))},ownsKey:function(e,t,n){YUITest.Assert._increment(),t.hasOwnProperty(e)||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e+"' not found on object instance."))},ownsKeys:function(e,t,n){YUITest.Assert._increment();for(var r=0;r<e.length;r++)t.hasOwnProperty(e[r])||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e[r]+"' not found on object instance."))},ownsNoKeys:function(e,t){YUITest.Assert._increment();var n=YUITest.Object.keys(e).length;n!==0&&YUITest.Assert.fail(YUITest.Assert._formatMessage(t,"Object owns "+n+" properties but should own none."))},ownsOrInheritsKey:function(e,t,n){YUITest.Assert._increment(),e in t||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e+"' not found on object."))},ownsOrInheritsKeys:function(e,t,n){YUITest.Assert._increment();for(var r=0;r<e.length;r++)e[r]in t||YUITest.Assert.fail(YUITest.Assert._formatMessage(n,"Property '"+e[r]+"' not found on object."))}},YUITest.Results=function(e){this.name=e,this.passed=0,this.failed=0,this.errors=0,this.ignored=0,this.total=0,this.duration=0},YUITest.Results.prototype.include=function(e){this.passed+=e.passed,this.failed+=e.failed,this.ignored+=e.ignored,this.total+=e.total,this.errors+=e.errors},YUITest.ShouldError=function(e){YUITest.AssertionError.call(this,e||"This test should have thrown an error but didn't."),this.name="ShouldError"},YUITest.ShouldError.prototype=new YUITest.AssertionError,YUITest.ShouldError.prototype.constructor=YUITest.ShouldError,YUITest.ShouldFail=function(e){YUITest.AssertionError.call(this,e||"This test should fail but didn't."),this.name="ShouldFail"},YUITest.ShouldFail.prototype=new YUITest.AssertionError,YUITest.ShouldFail.prototype.constructor=YUITest.ShouldFail,YUITest.UnexpectedError=function(e){YUITest.AssertionError.call(this,"Unexpected error: "+e.message),this.cause=e,this.name="UnexpectedError",this.stack=e.stack},YUITest.UnexpectedError.prototype=new YUITest.AssertionError,YUITest.UnexpectedError.prototype.constructor=YUITest.UnexpectedError,YUITest.UnexpectedValue=function(e,t){YUITest.AssertionError.call(this,e),this.unexpected=t,this.name="UnexpectedValue"},YUITest.UnexpectedValue.prototype=new YUITest.AssertionError,YUITest.UnexpectedValue.prototype.constructor=YUITest.UnexpectedValue
,YUITest.UnexpectedValue.prototype.getMessage=function(){return this.message+"\nUnexpected: "+this.unexpected+" ("+typeof this.unexpected+") "},YUITest.Wait=function(e,t){this.segment=typeof e=="function"?e:null,this.delay=typeof t=="number"?t:0},e.Test=YUITest,e.Object.each(YUITest,function(t,n){var n=n.replace("Test","");e.Test[n]=t})),e.Assert=YUITest.Assert,e.Assert.Error=e.Test.AssertionError,e.Assert.ComparisonFailure=e.Test.ComparisonFailure,e.Assert.UnexpectedValue=e.Test.UnexpectedValue,e.Mock=e.Test.Mock,e.ObjectAssert=e.Test.ObjectAssert,e.ArrayAssert=e.Test.ArrayAssert,e.DateAssert=e.Test.DateAssert,e.Test.ResultsFormat=e.Test.TestFormat;var n=e.Test.ArrayAssert.itemsAreEqual;e.Test.ArrayAssert.itemsAreEqual=function(t,r,i){return n.call(this,e.Array(t),e.Array(r),i)},e.assert=function(t,n){e.Assert._increment();if(!t)throw new e.Assert.Error(e.Assert._formatMessage(n,"Assertion failed."))},e.fail=e.Assert.fail,e.Test.Runner.once=e.Test.Runner.subscribe,e.Test.Runner.disableLogging=function(){e.Test.Runner._log=!1},e.Test.Runner.enableLogging=function(){e.Test.Runner._log=!0},e.Test.Runner._ignoreEmpty=!0,e.Test.Runner._log=!0,e.Test.Runner.on=e.Test.Runner.attach;if(!YUI.YUITest){e.config.win&&(e.config.win.YUITest=YUITest),YUI.YUITest=e.Test;var r=function(t){var n="",r="";switch(t.type){case this.BEGIN_EVENT:n="Testing began at "+(new Date).toString()+".",r="info";break;case this.COMPLETE_EVENT:n=e.Lang.sub("Testing completed at "+(new Date).toString()+".\n"+"Passed:{passed} Failed:{failed} "+"Total:{total} ({ignored} ignored)",t.results),r="info";break;case this.TEST_FAIL_EVENT:n=t.testName+": failed.\n"+t.error.getMessage(),r="fail";break;case this.TEST_IGNORE_EVENT:n=t.testName+": ignored.",r="ignore";break;case this.TEST_PASS_EVENT:n=t.testName+": passed.",r="pass";break;case this.TEST_SUITE_BEGIN_EVENT:n='Test suite "'+t.testSuite.name+'" started.',r="info";break;case this.TEST_SUITE_COMPLETE_EVENT:n=e.Lang.sub('Test suite "'+t.testSuite.name+'" completed'+".\n"+"Passed:{passed} Failed:{failed} "+"Total:{total} ({ignored} ignored)",t.results),r="info";break;case this.TEST_CASE_BEGIN_EVENT:n='Test case "'+t.testCase.name+'" started.',r="info";break;case this.TEST_CASE_COMPLETE_EVENT:n=e.Lang.sub('Test case "'+t.testCase.name+'" completed.\n'+"Passed:{passed} Failed:{failed} "+"Total:{total} ({ignored} ignored)",t.results),r="info";break;default:n="Unexpected event "+t.type,r="info"}e.Test.Runner._log&&e.log(n,r,"TestRunner")},i,s;for(i in e.Test.Runner)s=e.Test.Runner[i],i.indexOf("_EVENT")>-1&&e.Test.Runner.subscribe(s,r)}},"3.12.0",{requires:["event-simulate","event-custom","json-stringify"]});
