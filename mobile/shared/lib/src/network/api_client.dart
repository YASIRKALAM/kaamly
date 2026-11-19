import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_endpoints.dart';
import '../models/api_response.dart';

class ApiClient {
  late final Dio _dio;
  final FlutterSecureStorage _storage;
  static const String _baseUrlStorageKey = 'custom_base_url';

  ApiClient({String? baseUrl, FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage() {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? ApiEndpoints.baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.read(key: 'auth_token');
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException e, handler) {
          return handler.next(e);
        },
      ),
    );
  }

  String get currentBaseUrl => _dio.options.baseUrl;

  Future<void> loadSavedBaseUrl() async {
    try {
      final saved = await _storage.read(key: _baseUrlStorageKey);
      if (saved != null && saved.trim().isNotEmpty) {
        _dio.options.baseUrl = saved.trim();
        ApiEndpoints.setBaseUrl(saved.trim());
      }
    } catch (_) {}
  }

  Future<void> updateBaseUrl(String newUrl) async {
    final trimmed = newUrl.trim();
    if (trimmed.isNotEmpty) {
      _dio.options.baseUrl = trimmed;
      ApiEndpoints.setBaseUrl(trimmed);
      try {
        await _storage.write(key: _baseUrlStorageKey, value: trimmed);
      } catch (_) {}
    }
  }

  Future<bool> testConnection() async {
    try {
      final res = await _dio.get(
        ApiEndpoints.categories,
        options: Options(
          sendTimeout: const Duration(seconds: 4),
          receiveTimeout: const Duration(seconds: 4),
        ),
      );
      return res.statusCode != null && res.statusCode! >= 200 && res.statusCode! < 500;
    } catch (_) {
      return false;
    }
  }

  Future<void> saveToken(String token) async {
    await _storage.write(key: 'auth_token', value: token);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: 'auth_token');
  }

  Future<String?> getToken() async {
    return await _storage.read(key: 'auth_token');
  }

  Future<ApiResponse<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJsonT,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParameters);
      return ApiResponse<T>.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      return _handleDioError<T>(e);
    } catch (e) {
      return ApiResponse<T>(success: false, message: e.toString());
    }
  }

  Future<ApiResponse<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJsonT,
  }) async {
    try {
      final response = await _dio.post(path, data: data, queryParameters: queryParameters);
      return ApiResponse<T>.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      return _handleDioError<T>(e);
    } catch (e) {
      return ApiResponse<T>(success: false, message: e.toString());
    }
  }

  ApiResponse<T> _handleDioError<T>(DioException e) {
    if (e.response != null && e.response?.data is Map<String, dynamic>) {
      final data = e.response!.data as Map<String, dynamic>;
      return ApiResponse<T>(
        success: false,
        message: data['message'] ?? 'An error occurred',
        errors: data['errors'] as Map<String, dynamic>?,
      );
    }
    return ApiResponse<T>(
      success: false,
      message: e.message ?? 'Network connection failed',
    );
  }
}
