import 'dart:convert';
import 'dart:async';

typedef WebSocketEventListener = void Function(Map<String, dynamic> data);

class ReverbClient {
  final String appKey;
  final String host;
  final int port;
  final String? authToken;
  final Map<String, List<WebSocketEventListener>> _listeners = {};

  ReverbClient({
    this.appKey = 'kaamly_reverb_key',
    this.host = '10.0.2.2',
    this.port = 8080,
    this.authToken,
  });

  void subscribe(String channel, String eventName, WebSocketEventListener listener) {
    final key = '$channel:$eventName';
    _listeners.putIfAbsent(key, () => []).add(listener);
  }

  void unsubscribe(String channel, String eventName, WebSocketEventListener listener) {
    final key = '$channel:$eventName';
    _listeners[key]?.remove(listener);
  }

  void handleIncomingMessage(String channel, String eventName, Map<String, dynamic> payload) {
    final key = '$channel:$eventName';
    final callbacks = _listeners[key];
    if (callbacks != null) {
      for (final callback in callbacks) {
        callback(payload);
      }
    }
  }
}
