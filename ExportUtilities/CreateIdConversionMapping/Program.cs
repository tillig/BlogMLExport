using System;
using System.Collections.Generic;
using System.Text;
using System.Xml;
using System.Web;
using System.Text.RegularExpressions;

namespace Paraesthesia.Tools.ExportUtilities.CreateIdConversionMapping
{
	class Program
	{
		static SortedList<int, string> Titles = new SortedList<int, string>();

		static void Main(string[] args)
		{
			if (args.Length != 2)
			{
				string progName = typeof(Program).Assembly.GetName().Name;
				Console.WriteLine(progName);
				Console.WriteLine("Creates a mapping between old post ID and new post ID for BlogML export and link update.");
				Console.WriteLine("Usage:");
				Console.WriteLine("{0} BlogMLFile OutputFile", progName);
				Console.WriteLine("Example:");
				Console.WriteLine("{0} blogml.xml idmap.xml");
				return;
			}
			string outputFilePath = args[1];

			try
			{
				XmlDocument blogMl = new XmlDocument();
				blogMl.Load(args[0]);

				XmlDocument output = new XmlDocument();
				output.AppendChild(output.CreateElement("blogmlidmap"));

				XmlNamespaceManager nsmgr = new XmlNamespaceManager(blogMl.NameTable);
				nsmgr.AddNamespace("a", blogMl.DocumentElement.NamespaceURI);

				XmlNodeList posts = blogMl.SelectNodes("a:blog/a:posts/a:post", nsmgr);

				foreach (XmlNode post in posts)
				{
					int oldid = Int32.Parse(post.Attributes["id"].Value);
					string title = post.SelectSingleNode("a:title", nsmgr).InnerText;
					string newid = NormalizeTitle(title);
					Titles.Add(oldid, newid);
				}

				int count = Titles.Count;
				for(int i = 0; i < count; i++)
				{
					int key = Titles.Keys[i];
					Pluralize(key);
				}

				foreach (XmlNode post in posts)
				{
					string oldid = post.Attributes["id"].Value;
					string title = post.SelectSingleNode("a:title", nsmgr).InnerText;
					string newid = Titles[Int32.Parse(oldid)];

					XmlElement mapping = output.CreateElement("map");
					XmlAttribute oldAttrib = output.CreateAttribute("old");
					oldAttrib.Value = oldid;
					mapping.Attributes.Append(oldAttrib);
					XmlAttribute newAttrib = output.CreateAttribute("new");
					newAttrib.Value = newid;
					mapping.Attributes.Append(newAttrib);
					DateTime postDate = DateTime.Parse(post.Attributes["date-created"].Value);
					XmlAttribute yearAttrib = output.CreateAttribute("year");
					yearAttrib.Value = String.Format("{0:D4}", postDate.Year);
					mapping.Attributes.Append(yearAttrib);
					XmlAttribute monthAttrib = output.CreateAttribute("month");
					monthAttrib.Value = String.Format("{0:D2}", postDate.Month);
					mapping.Attributes.Append(monthAttrib);
					XmlAttribute dayAttrib = output.CreateAttribute("day");
					dayAttrib.Value = String.Format("{0:D2}", postDate.Day);
					mapping.Attributes.Append(dayAttrib);

					output.DocumentElement.AppendChild(mapping);
				}

				using (XmlTextWriter writer = new XmlTextWriter(outputFilePath, Encoding.UTF8))
                {
					writer.Formatting = Formatting.Indented;
					writer.Indentation = 1;
					writer.IndentChar = '\t';
					output.WriteTo(writer);
                }

				Console.WriteLine("Done.");
			}
			catch (Exception err)
			{
				Console.WriteLine("{0}", err);
				Console.ReadLine();
			}


		}

		private const int LimitWordCount = 10;
		private const char SeparatingCharacter = '-';

		private static string NormalizeTitle(string title)
		{
			string[] wordArray = title.Split(" ".ToCharArray());
			if (wordArray.Length > LimitWordCount)
			{
				int letterCount = 0;
				for (int i = 0; i < LimitWordCount; i++)
				{
					letterCount = (letterCount + wordArray[i].Length) + 1;
				}
				title = title.Substring(0, letterCount - 1);
			}

			string cleanTitle = RemoveDoublePeriods(RemoveTrailingPeriods(HttpUtility.UrlEncode(ReplaceSpacesWithSeparator(RemoveNonWordCharacters(title), SeparatingCharacter))).Trim(new char[] { SeparatingCharacter }));
			if (IsNumeric(cleanTitle))
			{
				cleanTitle = "n" + SeparatingCharacter + cleanTitle;
			}

			return cleanTitle.ToLower();
		}

		private static void Pluralize(int id)
		{
			if (!Titles.ContainsKey(id))
			{
				return;
			}
			int position = -1;
			foreach (KeyValuePair<int, string> mapping in Titles)
			{
				if (mapping.Value == Titles[id])
				{
					position++;
				}
				if (mapping.Key == id)
				{
					break;
				}
			}

			string entryName = Titles[id];
			switch (position)
			{
				case 1:
					entryName = entryName + SeparatingCharacter + "Again";
					break;

				case 2:
					entryName = entryName + SeparatingCharacter + "Yet" + SeparatingCharacter + "Again";
					break;

				case 3:
					entryName = entryName + SeparatingCharacter + "And" + SeparatingCharacter + "Again";
					break;

				case 4:
					entryName = entryName + SeparatingCharacter + "Once" + SeparatingCharacter + "More";
					break;

				case 5:
					entryName = entryName + SeparatingCharacter + "To" + SeparatingCharacter + "Beat" + SeparatingCharacter + "A" + SeparatingCharacter + "Dead" + SeparatingCharacter + "Horse";
					break;

				default: break;
			}
			Titles[id] = entryName.ToLower();
		}

		private static string RemoveDoublePeriods(string text)
		{
			while (text.IndexOf("..") > -1)
			{
				text = text.Replace("..", ".");
			}
			return text;
		}

		private static string RemoveTrailingPeriods(string text)
		{
			Regex regex = new Regex(@"\.+$", RegexOptions.Compiled);
			return regex.Replace(text, string.Empty);
		}

		private static string ReplaceSpacesWithSeparator(string text, char wordSeparator)
		{
			if (wordSeparator == '\0')
			{
				return PascalCase(text);
			}
			return text.Replace(' ', wordSeparator);
		}

		public static string PascalCase(string text)
		{
			if (text == null)
			{
				throw new ArgumentNullException("text", "Cannot PascalCase null text.");
			}
			if (text.Length == 0)
			{
				return text;
			}
			string[] textArray = text.Split(new char[] { ' ' });
			for (int i = 0; i < textArray.Length; i++)
			{
				if (textArray[i].Length > 0)
				{
					string text2 = textArray[i];
					char ch = char.ToUpper(text2[0]);
					textArray[i] = ch + text2.Substring(1);
				}
			}
			return string.Join(string.Empty, textArray);
		}

		private static string RemoveNonWordCharacters(string text)
		{
			MatchCollection matchs = new Regex(@"[\w\d\.\- ]+", RegexOptions.Compiled).Matches(text);
			string text2 = string.Empty;
			foreach (Match match in matchs)
			{
				if (match.Value.Length > 0)
				{
					text2 = text2 + match.Value;
				}
			}
			return text2;
		}

		public static bool IsNumeric(string text)
		{
			return Regex.IsMatch(text, @"^\d+$");
		}

		public static string XmlEncode(string text)
		{
			return text.Replace("&", "&amp;").Replace("\"", "&quot;").Replace("<", "&lt;").Replace(">", "&gt;");
		}


	}
}
