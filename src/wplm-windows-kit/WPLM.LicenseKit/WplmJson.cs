using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;

namespace WPLM.LicenseKit
{
    /// <summary>
    /// JSON миниمال — parser و serializer بدون وابستگی خارجی.
    /// برای پاسخ‌های API سرور (objects، arrays، strings، numbers، booleans)
    /// طراحی شده است.
    /// </summary>
    public static class WplmJson
    {
        public static string Serialize(object obj)
        {
            var sb = new StringBuilder();
            WriteValue(sb, obj);
            return sb.ToString();
        }

        private static void WriteValue(StringBuilder sb, object value)
        {
            if (value == null)
            {
                sb.Append("null");
            }
            else if (value is string s)
            {
                WriteString(sb, s);
            }
            else if (value is bool b)
            {
                sb.Append(b ? "true" : "false");
            }
            else if (value is int i)
            {
                sb.Append(i.ToString());
            }
            else if (value is long l)
            {
                sb.Append(l.ToString());
            }
            else if (value is double d)
            {
                sb.Append(d.ToString(System.Globalization.CultureInfo.InvariantCulture));
            }
            else if (value is Dictionary<string, object> dict)
            {
                WriteObject(sb, dict);
            }
            else if (value is List<object> list)
            {
                WriteArray(sb, list);
            }
            else if (value is object[] arr)
            {
                WriteArray(sb, arr.Cast<object>().ToList());
            }
            else
            {
                WriteString(sb, Convert.ToString(value));
            }
        }

        private static void WriteString(StringBuilder sb, string s)
        {
            sb.Append('"');
            foreach (var c in s)
            {
                switch (c)
                {
                    case '"': sb.Append("\\\""); break;
                    case '\\': sb.Append("\\\\"); break;
                    case '\n': sb.Append("\\n"); break;
                    case '\r': sb.Append("\\r"); break;
                    case '\t': sb.Append("\\t"); break;
                    default:
                        if (c < 32)
                        {
                            sb.Append("\\u").Append(((int)c).ToString("x4"));
                        }
                        else
                        {
                            sb.Append(c);
                        }
                        break;
                }
            }
            sb.Append('"');
        }

        private static void WriteObject(StringBuilder sb, Dictionary<string, object> dict)
        {
            sb.Append('{');
            var first = true;
            foreach (var kv in dict)
            {
                if (!first) sb.Append(',');
                first = false;
                WriteString(sb, kv.Key);
                sb.Append(':');
                WriteValue(sb, kv.Value);
            }
            sb.Append('}');
        }

        private static void WriteArray(StringBuilder sb, List<object> list)
        {
            sb.Append('[');
            for (var i = 0; i < list.Count; i++)
            {
                if (i > 0) sb.Append(',');
                WriteValue(sb, list[i]);
            }
            sb.Append(']');
        }

        // ---------- Parser ----------

        public static Dictionary<string, object> ParseObject(string json)
        {
            var parser = new Parser(json);
            var result = parser.ParseValue();
            return result as Dictionary<string, object>;
        }

        private sealed class Parser
        {
            private readonly string _s;
            private int _pos;

            public Parser(string s)
            {
                _s = s ?? "";
            }

            public object ParseValue()
            {
                SkipWhitespace();
                if (_pos >= _s.Length)
                {
                    return null;
                }

                var c = _s[_pos];
                switch (c)
                {
                    case '{':
                        return ParseObjectInternal();
                    case '[':
                        return ParseArrayInternal();
                    case '"':
                        return ParseString();
                    case 't':
                        Expect("true");
                        return true;
                    case 'f':
                        Expect("false");
                        return false;
                    case 'n':
                        Expect("null");
                        return null;
                    default:
                        return ParseNumber();
                }
            }

            private void SkipWhitespace()
            {
                while (_pos < _s.Length && char.IsWhiteSpace(_s[_pos]))
                {
                    _pos++;
                }
            }

            private void Expect(string literal)
            {
                if (_pos + literal.Length > _s.Length || _s.Substring(_pos, literal.Length) != literal)
                {
                    throw new WplmApiException("خطای JSON: انتظار '" + literal + "'.", "json_error");
                }
                _pos += literal.Length;
            }

            private Dictionary<string, object> ParseObjectInternal()
            {
                var result = new Dictionary<string, object>();
                _pos++; // {
                SkipWhitespace();
                if (_pos < _s.Length && _s[_pos] == '}')
                {
                    _pos++;
                    return result;
                }

                while (_pos < _s.Length)
                {
                    SkipWhitespace();
                    var key = ParseString();
                    SkipWhitespace();
                    if (_pos >= _s.Length || _s[_pos] != ':')
                    {
                        throw new WplmApiException("خطای JSON: انتظار ':'.", "json_error");
                    }
                    _pos++;
                    var value = ParseValue();
                    result[key] = value;
                    SkipWhitespace();
                    if (_pos >= _s.Length)
                    {
                        break;
                    }
                    if (_s[_pos] == ',')
                    {
                        _pos++;
                        continue;
                    }
                    if (_s[_pos] == '}')
                    {
                        _pos++;
                        break;
                    }
                    throw new WplmApiException("خطای JSON: انتظار ',' یا '}'.", "json_error");
                }
                return result;
            }

            private List<object> ParseArrayInternal()
            {
                var result = new List<object>();
                _pos++; // [
                SkipWhitespace();
                if (_pos < _s.Length && _s[_pos] == ']')
                {
                    _pos++;
                    return result;
                }

                while (_pos < _s.Length)
                {
                    var value = ParseValue();
                    result.Add(value);
                    SkipWhitespace();
                    if (_pos >= _s.Length)
                    {
                        break;
                    }
                    if (_s[_pos] == ',')
                    {
                        _pos++;
                        continue;
                    }
                    if (_s[_pos] == ']')
                    {
                        _pos++;
                        break;
                    }
                    throw new WplmApiException("خطای JSON: انتظار ',' یا ']'.", "json_error");
                }
                return result;
            }

            private string ParseString()
            {
                SkipWhitespace();
                if (_pos >= _s.Length || _s[_pos] != '"')
                {
                    throw new WplmApiException("خطای JSON: انتظار '\"'.", "json_error");
                }
                _pos++;
                var sb = new StringBuilder();
                while (_pos < _s.Length)
                {
                    var c = _s[_pos];
                    if (c == '"')
                    {
                        _pos++;
                        return sb.ToString();
                    }
                    if (c == '\\')
                    {
                        _pos++;
                        if (_pos >= _s.Length)
                        {
                            throw new WplmApiException("خطای JSON: escape نامعتبر.", "json_error");
                        }
                        var esc = _s[_pos];
                        switch (esc)
                        {
                            case '"': sb.Append('"'); break;
                            case '\\': sb.Append('\\'); break;
                            case '/': sb.Append('/'); break;
                            case 'n': sb.Append('\n'); break;
                            case 'r': sb.Append('\r'); break;
                            case 't': sb.Append('\t'); break;
                            case 'b': sb.Append('\b'); break;
                            case 'f': sb.Append('\f'); break;
                            case 'u':
                                _pos++;
                                if (_pos + 4 > _s.Length)
                                {
                                    throw new WplmApiException("خطای JSON: escape \u نامعتبر.", "json_error");
                                }
                                sb.Append((char)Convert.ToInt32(_s.Substring(_pos, 4), 16));
                                _pos += 4;
                                break;
                            default:
                                sb.Append(esc);
                                break;
                        }
                        _pos++;
                    }
                    else
                    {
                        sb.Append(c);
                        _pos++;
                    }
                }
                throw new WplmApiException("خطای JSON: string بسته نشده.", "json_error");
            }

            private object ParseNumber()
            {
                SkipWhitespace();
                var start = _pos;
                if (_pos < _s.Length && (_s[_pos] == '-' || _s[_pos] == '+'))
                {
                    _pos++;
                }
                var hasDot = false;
                var hasExp = false;
                while (_pos < _s.Length)
                {
                    var c = _s[_pos];
                    if (char.IsDigit(c))
                    {
                        _pos++;
                    }
                    else if (c == '.' && !hasDot)
                    {
                        hasDot = true;
                        _pos++;
                    }
                    else if ((c == 'e' || c == 'E') && !hasExp)
                    {
                        hasExp = true;
                        _pos++;
                        if (_pos < _s.Length && (_s[_pos] == '-' || _s[_pos] == '+'))
                        {
                            _pos++;
                        }
                    }
                    else
                    {
                        break;
                    }
                }
                var numStr = _s.Substring(start, _pos - start);
                if (numStr.Length == 0)
                {
                    throw new WplmApiException("خطای JSON: عدد نامعتبر.", "json_error");
                }
                if (numStr.Contains(".") || numStr.Contains("e") || numStr.Contains("E"))
                {
                    return double.Parse(numStr, System.Globalization.CultureInfo.InvariantCulture);
                }
                long.TryParse(numStr, out var longVal);
                return longVal;
            }
        }

        // ---------- Helpers ----------

        public static string GetString(Dictionary<string, object> dict, string key)
        {
            if (dict == null || !dict.TryGetValue(key, out var value) || value == null)
            {
                return "";
            }
            return Convert.ToString(value);
        }

        public static bool GetBool(Dictionary<string, object> dict, string key)
        {
            if (dict == null || !dict.TryGetValue(key, out var value) || value == null)
            {
                return false;
            }
            return Convert.ToBoolean(value);
        }

        public static long GetLong(Dictionary<string, object> dict, string key)
        {
            if (dict == null || !dict.TryGetValue(key, out var value) || value == null)
            {
                return 0;
            }
            if (value is long l) return l;
            if (value is double d) return (long)d;
            long.TryParse(Convert.ToString(value), out var parsed);
            return parsed;
        }
    }
}
