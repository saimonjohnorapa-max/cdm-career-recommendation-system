export function isMobileExamDevice(device = navigator) {
  return device.userAgentData?.mobile === true
    || /android|iphone|ipad|ipod|mobile|tablet|kindle|silk|opera mini|windows phone/i.test(device.userAgent || '')
    || (/Macintosh/i.test(device.userAgent || '') && device.maxTouchPoints > 1);
}
