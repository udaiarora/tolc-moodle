/*
YUI 3.12.0 (build 8655935)
Copyright 2013 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/

YUI.add("attribute-base",function(e,t){function n(){e.AttributeCore.apply(this,arguments),e.AttributeObservable.apply(this,arguments),e.AttributeExtras.apply(this,arguments)}e.mix(n,e.AttributeCore,!1,null,1),e.mix(n,e.AttributeExtras,!1,null,1),e.mix(n,e.AttributeObservable,!0,null,1),n.INVALID_VALUE=e.AttributeCore.INVALID_VALUE,n._ATTR_CFG=e.AttributeCore._ATTR_CFG.concat(e.AttributeObservable._ATTR_CFG),n.protectAttrs=e.AttributeCore.protectAttrs,e.Attribute=n},"3.12.0",{requires:["attribute-core","attribute-observable","attribute-extras"]});
