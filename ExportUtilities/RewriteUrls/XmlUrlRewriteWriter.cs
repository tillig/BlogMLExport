using System;
using System.Collections.Generic;
using System.Text;
using System.Text.RegularExpressions;

namespace RewriteUrls
{
	public class XmlUrlRewriteWriter : System.Xml.XmlTextWriter
	{
		public Dictionary<string, string> EntryIdMappings = new Dictionary<string, string>();

		public XmlUrlRewriteWriter(System.IO.TextWriter w) : base(w) { }
		public XmlUrlRewriteWriter(string filename, Encoding encoding) : base(filename, encoding) { }
		public XmlUrlRewriteWriter(System.IO.Stream w, Encoding encoding) : base(w, encoding) { }

		private bool _rewriteUrls = false;

		public override void WriteStartElement(string prefix, string localName, string ns)
		{
			if (localName == "content")
			{
				this._rewriteUrls = true;
			}
			base.WriteStartElement(prefix, localName, ns);
		}

		public override void WriteEndElement()
		{
			this._rewriteUrls = false;
			base.WriteEndElement();
		}

		public override void WriteCData(string text)
		{
			if (this._rewriteUrls)
			{
				text = this.UpdateUrls(text);
			}
			base.WriteCData(text);
		}

		public override void WriteFullEndElement()
		{
			this._rewriteUrls = false;
			base.WriteFullEndElement();
		}

		public override void WriteValue(string value)
		{
			if (this._rewriteUrls)
			{
				value = this.UpdateUrls(value);
			}
			base.WriteValue(value);
		}

		private string UpdateUrls(string input)
		{
			if (String.IsNullOrEmpty(input))
			{
				return input;
			}

			MatchEvaluator replaceUrlMatch = new MatchEvaluator(this.ReplaceUrlMatch);
			string retVal = Regex.Replace(input, "\"(?<server>http://www.paraesthesia.com)?(?<url>/[^?\"]*)(?<qstring>\\?[^\"]+)?\"", replaceUrlMatch, RegexOptions.ExplicitCapture | RegexOptions.IgnoreCase | RegexOptions.Singleline);

			return retVal;
		}

		private string ReplaceUrlMatch(Match match)
		{
			string originalUrl = match.Value;
			if (String.IsNullOrEmpty(match.Groups["url"].Value))
			{
				return originalUrl;
			}

			// /blog to /
			// /blog/weblog.php to /
			// /blog/comments.php to /
			if (
				match.Groups["url"].Value == "/blog" ||
				(String.IsNullOrEmpty(match.Groups["qstring"].Value) && (match.Groups["url"].Value == "/blog/weblog.php" || match.Groups["url"].Value == "/blog/comments.php"))
				)
			{
				return "\"http://www.paraesthesia.com/\"";
			}
			// /blog/index.xml to feedburner
			if (match.Groups["url"].Value == "/blog/index.xml")
			{
				return "\"http://feeds.feedburner.com/Paraesthesia\"";
			}
			// /images/* to /images/pMachine/*
			if (originalUrl.Contains("/images/"))
			{
				return originalUrl.Replace("non-image/", "").Replace("/blog/images/uploads/", "/images/pMachine/");
			}
			// /blog/weblog.php?id=* to Subtext ID
			// /blog/comments.php?id=* to Subtext ID
			if (match.Groups["qstring"].Value.Contains("?id="))
			{
				Match parsedQueryString = Regex.Match(match.Groups["qstring"].Value, @"\?id=(?<qualifier>\D?)(?<id>\d+)");
				string oldIdToParse = parsedQueryString.Groups["id"].Value;
				string qualifier = parsedQueryString.Groups["qualifier"].Value;
				if (String.IsNullOrEmpty(oldIdToParse))
				{
					return originalUrl;
				}
				if(qualifier == "C")
				{
					return "\"http://www.paraesthesia.com/\"";
				}

				string mapped = this.EntryIdMappings[oldIdToParse];
				if (String.IsNullOrEmpty(mapped))
				{
					return originalUrl;
				}
				return String.Format("\"{0}\"", mapped);
			}

			return originalUrl;
		}
	}
}
